<?php

namespace App\Services\Activation;

use App\Models\NadiActivationGrant;
use App\Models\NadiApplication;
use App\Models\NadiApprovalLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivationAcknowledgementService
{
    public function __construct(
        protected ActivationGrantService $grantService
    )
    {
    }

    
    public function acknowledge(
        string $agentId,
        string $requestId,
        string $applicationId,
        string $grantId,
        string $result,
        ?string $errorCode = null,
        ?string $message = null
    ): array {
        return DB::transaction(function () use (
            $agentId,
            $requestId,
            $applicationId,
            $grantId,
            $result,
            $errorCode,
            $message
        )
        {
            $grant = NadiActivationGrant::where('id', $grantId)
                ->lockForUpdate()
                ->first();

            if (!$grant)
            {
                throw new RuntimeException(
                    'Activation grant not found.'
                );
            }

            if (
                $grant->agent_id !== $agentId
                || $grant->application_id !== $applicationId
            )
            {
                throw new RuntimeException(
                    'Activation grant identity mismatch.'
                );
            }

            $application = NadiApplication::where('id', $applicationId)
                ->lockForUpdate()
                ->first();

            if (!$application || $application->agent_id !== $agentId)
            {
                throw new RuntimeException(
                    'Application identity mismatch.'
                );
            }

            $registration = $grant->registration;

            if (
                !$registration
                || $registration->id !== $grant->registration_id
                || $registration->application_id !== $applicationId
                || $registration->agent_id !== $agentId
                || $registration->registration_status !== 'approved'
            )
            {
                throw new RuntimeException(
                    'Activation grant registration is invalid.'
                );
            }

            // A request ID cannot belong to another grant.

            $requestAlreadyUsed = NadiActivationGrant::where(
                'acknowledgement_request_id',
                $requestId
            )
                ->where('id', '!=', $grant->id)
                ->exists();

            if ($requestAlreadyUsed)
            {
                throw new RuntimeException(
                    'Acknowledgement request ID has already been used.'
                );
            }

            if ($grant->acknowledgement_status !== 'pending')
            {
                $expectedErrorCode = $result === 'failed'
                    ? $errorCode
                    : null;

                $expectedMessage = $result === 'failed'
                    ? $message
                    : null;

                $isExactReplay = (
                    $grant->acknowledgement_request_id === $requestId
                    && $grant->acknowledgement_status === $result
                    && $grant->acknowledgement_error_code === $expectedErrorCode
                    && $grant->acknowledgement_message === $expectedMessage
                );

                if ($isExactReplay)
                {
                    return [
                        'replayed' => true,
                        'acknowledgement_status' => $grant->acknowledgement_status,
                        'application_status' => $application->activation_status,
                    ];
                }

                throw new RuntimeException(
                    'Activation grant has already been acknowledged.'
                );
            }

            if (!$this->grantService->verify($grant))
            {
                throw new RuntimeException(
                    'Activation grant is invalid, expired, or revoked.'
                );
            }

            if (
                ($grant->grant_payload['registration_id'] ?? null) !== $registration->id
                || ($grant->grant_payload['application_id'] ?? null) !== $applicationId
                || ($grant->grant_payload['agent_id'] ?? null) !== $agentId
                || ($grant->grant_payload['server_id'] ?? null) !== $application->agent?->server_id
            )
            {
                throw new RuntimeException(
                    'Activation grant payload identity mismatch.'
                );
            }

            $grant->acknowledgement_status = $result;
            $grant->acknowledgement_request_id = $requestId;
            $grant->acknowledged_at = now('UTC');
            $grant->acknowledgement_error_code = $result === 'failed'
                ? $errorCode
                : null;
            $grant->acknowledgement_message = $result === 'failed'
                ? $message
                : null;

            $grant->save();

            if ($result === 'success')
            {
                $application->activation_status = 'active';

                if ($application->monitoring_suspended_at === null)
                {
                    $application->monitoring_status = 'active';
                }
            }
            else
            {
                $application->activation_status = 'failed';
                $application->monitoring_status = 'dormant';
            }

            $application->save();

            NadiApprovalLog::create([
                'registration_id' => $registration->id,
                'application_id' => $application->id,
                'admin_id' => null,
                'action' => $result === 'success'
                    ? 'activation_acknowledged'
                    : 'activation_failed',
                'reason' => $result === 'failed'
                    ? trim(($errorCode ?? 'AGENT_ACTIVATION_FAILED') . ': ' . ($message ?? 'Agent reported activation failure.'))
                    : 'Agent successfully verified activation grant.',
            ]);

            return [
                'replayed' => false,
                'acknowledgement_status' => $grant->acknowledgement_status,
                'application_status' => $application->activation_status,
            ];
        }, 3);
    }

}
