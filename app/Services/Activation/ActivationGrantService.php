<?php

namespace App\Services\Activation;

use App\Models\NadiActivationGrant;
use App\Models\NadiApplication;
use App\Models\NadiApprovalLog;
use App\Models\NadiRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ActivationGrantService
{
    public function __construct(
        protected GrantPayloadBuilder $payloadBuilder,
        protected GrantSigner $signer
    )
    {
    }

    public function issue(
        string $registrationId,
        ?string $adminId = null
    ): NadiActivationGrant
    {
        return DB::transaction(function () use (
            $registrationId,
            $adminId
        )
        {
            $registration = NadiRegistration::where('id', $registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->registration_status !== 'approved')
            {
                throw new RuntimeException(
                    'Only approved registrations can receive activation grants.'
                );
            }

            $application = NadiApplication::where(
                'id',
                $registration->application_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $agent = $registration->agent;
            $server = $agent?->server;

            if (!$agent || !$server)
            {
                throw new RuntimeException(
                    'Registration identity relationship is incomplete.'
                );
            }

            if ($application->agent_id !== $agent->id)
            {
                throw new RuntimeException(
                    'Application and registration agent identity mismatch.'
                );
            }

            $now = now('UTC');

            $existingGrant = NadiActivationGrant::where(
                'registration_id',
                $registration->id
            )
                ->where('grant_status', 'active')
                ->where('acknowledgement_status', '!=', 'failed')
                ->where('expires_at', '>', $now)
                ->orderByDesc('grant_version')
                ->lockForUpdate()
                ->first();

            if ($existingGrant)
            {
                $payload = $existingGrant->grant_payload;

                $payloadGrantId = $payload['grant_id'] ?? null;

                if (
                    !$payloadGrantId
                    || !Str::isUuid($payloadGrantId)
                )
                {
                    throw new RuntimeException(
                        'Existing activation grant has an invalid grant ID.'
                    );
                }

                if (!$this->signer->verify(
                    $payload,
                    $existingGrant->signature
                ))
                {
                    throw new RuntimeException(
                        'Existing activation grant signature is invalid.'
                    );
                }

                if (
                    ($payload['registration_id'] ?? null) !== $registration->id
                    || ($payload['application_id'] ?? null) !== $application->id
                    || ($payload['agent_id'] ?? null) !== $agent->id
                    || ($payload['server_id'] ?? null) !== $server->id
                )
                {
                    throw new RuntimeException(
                        'Existing activation grant identity mismatch.'
                    );
                }

                if ($existingGrant->id !== $payloadGrantId)
                {
                    $targetExists = NadiActivationGrant::where(
                        'id',
                        $payloadGrantId
                    )->exists();

                    if ($targetExists)
                    {
                        throw new RuntimeException(
                            'Activation grant ID reconciliation conflict.'
                        );
                    }

                    DB::table('nadi_activation_grants')
                        ->where('id', $existingGrant->id)
                        ->update([
                            'id' => $payloadGrantId,
                        ]);

                    $existingGrant = NadiActivationGrant::findOrFail(
                        $payloadGrantId
                    );
                }

                if ($existingGrant->acknowledgement_status === 'success')
                {
                    $application->activation_status = 'active';
                }
                elseif ($existingGrant->acknowledgement_status === 'failed')
                {
                    $application->activation_status = 'failed';
                }
                else
                {
                    $application->activation_status = 'pending';
                    $application->monitoring_status = 'dormant';
                }

                $application->save();

                return $existingGrant;
            }

            NadiActivationGrant::where(
                'registration_id',
                $registration->id
            )
                ->where('grant_status', 'active')
                ->where('expires_at', '<=', $now)
                ->update([
                    'grant_status' => 'expired',
                ]);

            $grantVersion = (
                NadiActivationGrant::where(
                    'registration_id',
                    $registration->id
                )->max('grant_version') ?? 0
            ) + 1;

            $keyId = config(
                'nadi.activation.key_id'
            );

            $lifetimeDays = (int) config(
                'nadi.activation.grant_lifetime_days',
                30
            );

            if ($lifetimeDays < 1)
            {
                throw new RuntimeException(
                    'Activation grant lifetime must be at least one day.'
                );
            }

            $payload = $this->payloadBuilder->build(
                $registration,
                $grantVersion,
                $keyId,
                $lifetimeDays
            );

            $signature = $this->signer->sign($payload);

            $grant = NadiActivationGrant::create([
                'id' => $payload['grant_id'],
                'application_id' => $application->id,
                'agent_id' => $agent->id,
                'registration_id' => $registration->id,
                'grant_version' => $grantVersion,
                'key_id' => $keyId,
                'grant_payload' => $payload,
                'signature' => $signature,
                'grant_status' => 'active',
                'acknowledgement_status' => 'pending',
                'issued_at' => $payload['issued_at'],
                'expires_at' => $payload['expires_at'],
            ]);

            $application->activation_status = 'pending';
            $application->monitoring_status = 'dormant';
            $application->save();

            NadiApprovalLog::create([
                'registration_id' => $registration->id,
                'application_id' => $application->id,
                'admin_id' => $adminId,
                'action' => 'activation_grant_issued',
                'reason' => 'Grant version ' . $grantVersion . ' issued; awaiting Agent acknowledgement.',
            ]);

            return $grant;
        }, 3);
    }

    public function revoke(
        string $grantId,
        ?string $adminId = null,
        ?string $reason = null
    ): NadiActivationGrant
    {
        return DB::transaction(function () use (
            $grantId,
            $adminId,
            $reason
        )
        {
            $grant = NadiActivationGrant::where('id', $grantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($grant->grant_status === 'revoked')
            {
                return $grant;
            }

            $grant->grant_status = 'revoked';
            $grant->revoked_at = now('UTC');
            $grant->save();

            $otherActiveGrant = NadiActivationGrant::where(
                'application_id',
                $grant->application_id
            )
                ->where('id', '!=', $grant->id)
                ->where('grant_status', 'active')
                ->where('expires_at', '>', now('UTC'))
                ->exists();

            if (!$otherActiveGrant)
            {
                $application = NadiApplication::where(
                    'id',
                    $grant->application_id
                )
                    ->lockForUpdate()
                    ->first();

                if ($application)
                {
                    $application->activation_status = 'revoked';
                    $application->monitoring_status = 'dormant';
                    $application->save();
                }
            }

            NadiApprovalLog::create([
                'registration_id' => $grant->registration_id,
                'application_id' => $grant->application_id,
                'admin_id' => $adminId,
                'action' => 'activation_grant_revoked',
                'reason' => $reason,
            ]);

            return $grant;
        }, 3);
    }

    public function verify(
        NadiActivationGrant $grant
    ): bool
    {
        if ($grant->grant_status !== 'active')
        {
            return false;
        }

        if (
            !$grant->expires_at
            || $grant->expires_at->isPast()
        )
        {
            return false;
        }

        if (
            ($grant->grant_payload['grant_id'] ?? null)
            !== $grant->id
        )
        {
            return false;
        }

        return $this->signer->verify(
            $grant->grant_payload,
            $grant->signature
        );
    }

    public function expireStaleGrants(): int
    {
        return DB::transaction(function ()
        {
            $staleGrants = NadiActivationGrant::where(
                'grant_status',
                'active'
            )
                ->where('expires_at', '<=', now('UTC'))
                ->lockForUpdate()
                ->get();

            $applicationIds = $staleGrants
                ->pluck('application_id')
                ->unique();

            foreach ($staleGrants as $grant)
            {
                $grant->grant_status = 'expired';
                $grant->save();
            }

            foreach ($applicationIds as $applicationId)
            {
                $otherActiveGrant = NadiActivationGrant::where(
                    'application_id',
                    $applicationId
                )
                    ->where('grant_status', 'active')
                    ->where('expires_at', '>', now('UTC'))
                    ->exists();

                if (!$otherActiveGrant)
                {
                    NadiApplication::where('id', $applicationId)
                        ->update([
                            'activation_status' => 'expired',
                            'monitoring_status' => 'dormant',
                        ]);
                }
            }

            return $staleGrants->count();
        }, 3);
    }
}
