<?php

namespace App\Services\Agent;

use App\Models\NadiAgent;
use App\Models\NadiAgentCredential;
use App\Models\NadiAgentCredentialDelivery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AgentCredentialService
{
    public function issue(
        NadiAgent $agent,
        int $lifetimeDays = 90
    ): array
    {
        return DB::transaction(function () use (
            $agent,
            $lifetimeDays
        )
        {
            $agent = NadiAgent::query()
                ->whereKey($agent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($agent->installation_state !== 'installed')
            {
                throw new RuntimeException(
                    'Credential cannot be issued to an uninstalled agent.'
                );
            }

            if ($lifetimeDays < 1)
            {
                throw new RuntimeException(
                    'Credential lifetime must be at least one day.'
                );
            }

            $activeCredential = NadiAgentCredential::query()
                ->where('agent_id', $agent->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($activeCredential)
            {
                throw new RuntimeException(
                    'This agent already has an active credential.'
                );
            }

            $plaintextToken = $this->generateToken();

            $credential = NadiAgentCredential::create([
                'id' => (string) Str::uuid(),
                'agent_id' => $agent->id,
                'credential_hash' => $this->hashToken($plaintextToken),
                'key_version' => $this->nextKeyVersion($agent->id),
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addDays($lifetimeDays),
                'revoked_at' => null,
                'last_used_at' => null,
            ]);

            return [
                'credential' => $credential,
                'token' => $plaintextToken,
            ];
        });
    }

    public function rotate(
        NadiAgent $agent,
        int $lifetimeDays = 90,
        int $deliveryMinutes = 15
    ): array
    {
        if ($lifetimeDays < 1 || $lifetimeDays > 365)
        {
            throw new RuntimeException(
                'Credential lifetime must be between 1 and 365 days.'
            );
        }

        if ($deliveryMinutes < 5 || $deliveryMinutes > 60)
        {
            throw new RuntimeException(
                'Credential delivery lifetime must be between 5 and 60 minutes.'
            );
        }

        return DB::transaction(function () use (
            $agent,
            $lifetimeDays,
            $deliveryMinutes
        )
        {
            $agent = NadiAgent::query()
                ->whereKey($agent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($agent->installation_state !== 'installed')
            {
                throw new RuntimeException(
                    'Credential rotation requires an installed agent.'
                );
            }

            $pendingDeliveries = NadiAgentCredentialDelivery::query()
                ->where('agent_id', $agent->id)
                ->where('delivery_status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($pendingDeliveries as $pendingDelivery)
            {
                if ($pendingDelivery->expires_at->isFuture())
                {
                    throw new RuntimeException(
                        'This agent already has a pending credential rotation.'
                    );
                }

                $this->expireDelivery($pendingDelivery);
            }

            $oldCredential = NadiAgentCredential::query()
                ->where('agent_id', $agent->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$oldCredential)
            {
                throw new RuntimeException(
                    'No valid active credential was found for this agent.'
                );
            }

            $plaintextToken = $this->generateToken();

            $newCredential = NadiAgentCredential::create([
                'id' => (string) Str::uuid(),
                'agent_id' => $agent->id,
                'credential_hash' => $this->hashToken($plaintextToken),
                'key_version' => $this->nextKeyVersion($agent->id),
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addDays($lifetimeDays),
                'revoked_at' => null,
                'last_used_at' => null,
            ]);

            $delivery = NadiAgentCredentialDelivery::create([
                'agent_id' => $agent->id,
                'old_credential_id' => $oldCredential->id,
                'new_credential_id' => $newCredential->id,
                'encrypted_token' => Crypt::encryptString(
                    $plaintextToken
                ),
                'delivery_status' => 'pending',
                'expires_at' => now()->addMinutes($deliveryMinutes),
                'retrieved_at' => null,
                'acknowledged_at' => null,
                'created_at' => now(),
            ]);

            return [
                'delivery' => $delivery,
                'old_credential' => $oldCredential,
                'new_credential' => $newCredential,
            ];
        });
    }
    
    public function retrievePendingDelivery(
        NadiAgent $agent,
        NadiAgentCredential $authenticatedCredential,
        string $deliveryId
    ): array {
        $result = DB::transaction(function () use (
            $agent,
            $authenticatedCredential,
            $deliveryId
        ) {
            $delivery = NadiAgentCredentialDelivery::query()
                ->whereKey($deliveryId)
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $delivery->old_credential_id !== $authenticatedCredential->id
            ) {
                throw new RuntimeException(
                    'The authenticated credential is not authorized for this delivery.'
                );
            }

            if ($delivery->delivery_status !== 'pending') {
                throw new RuntimeException(
                    'Credential delivery is no longer pending.'
                );
            }

            if ($delivery->expires_at->isPast()) {
                $this->expireDelivery($delivery);

                return [
                    'expired' => true,
                ];
            }

            $delivery->update([
                'retrieved_at' => now(),
            ]);

            return [
                'expired' => false,
                'data' => [
                    'delivery_id' => $delivery->id,
                    'credential_id' => $delivery->new_credential_id,
                    'token' => Crypt::decryptString(
                        $delivery->encrypted_token
                    ),
                    'expires_at' => $delivery->expires_at,
                ],
            ];
        });

        if ($result['expired']) {
            throw new RuntimeException(
                'Credential delivery has expired.'
            );
        }

        return $result['data'];
    }

    public function acknowledgeRotation(
        NadiAgent $agent,
        NadiAgentCredential $authenticatedCredential,
        string $deliveryId
    ): NadiAgentCredential {
        $result = DB::transaction(function () use (
            $agent,
            $authenticatedCredential,
            $deliveryId
        ) {
            $delivery = NadiAgentCredentialDelivery::query()
                ->whereKey($deliveryId)
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $delivery->new_credential_id !== $authenticatedCredential->id
            ) {
                throw new RuntimeException(
                    'ACK must be authenticated using the new credential.'
                );
            }

            if ($delivery->delivery_status === 'acknowledged') {
                return [
                    'expired' => false,
                    'credential' => $authenticatedCredential->fresh(),
                ];
            }

            if ($delivery->delivery_status !== 'pending') {
                throw new RuntimeException(
                    'Credential delivery is not awaiting acknowledgement.'
                );
            }

            if ($delivery->expires_at->isPast()) {
                $this->expireDelivery($delivery);

                return [
                    'expired' => true,
                ];
            }

            $newCredential = NadiAgentCredential::query()
                ->whereKey($delivery->new_credential_id)
                ->where('agent_id', $agent->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$newCredential) {
                throw new RuntimeException(
                    'The new credential is invalid or inactive.'
                );
            }

            $oldCredential = NadiAgentCredential::query()
                ->whereKey($delivery->old_credential_id)
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (!$oldCredential) {
                throw new RuntimeException(
                    'The previous credential could not be found.'
                );
            }

            $oldCredential->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            $delivery->update([
                'delivery_status' => 'acknowledged',
                'acknowledged_at' => now(),
                'encrypted_token' => '',
            ]);

            return [
                'expired' => false,
                'credential' => $newCredential->fresh(),
            ];
        });

        if ($result['expired']) {
            throw new RuntimeException(
                'Credential delivery has expired.'
            );
        }

        return $result['credential'];
    }


    public function expirePendingDeliveries(
        ?NadiAgent $agent = null
    ): int
    {
        return DB::transaction(function () use ($agent)
        {
            $query = NadiAgentCredentialDelivery::query()
                ->where('delivery_status', 'pending')
                ->where('expires_at', '<=', now());

            if ($agent)
            {
                $query->where('agent_id', $agent->id);
            }

            $deliveries = $query
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery)
            {
                $this->expireDelivery($delivery);
            }

            return $deliveries->count();
        });
    }

    public function revoke(
        NadiAgentCredential $credential,
        ?string $reason = null
    ): NadiAgentCredential
    {
        return DB::transaction(function () use (
            $credential,
            $reason
        )
        {
            $credential = NadiAgentCredential::query()
                ->whereKey($credential->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($credential->status === 'revoked')
            {
                return $credential;
            }

            $deliveries = NadiAgentCredentialDelivery::query()
                ->where(function ($query) use ($credential)
                {
                    $query->where(
                        'old_credential_id',
                        $credential->id
                    )->orWhere(
                        'new_credential_id',
                        $credential->id
                    );
                })
                ->where('delivery_status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery)
            {
                $this->cancelDelivery($delivery);
            }

            $credential->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            return $credential->fresh();
        });
    }

    public function revokeForAgent(
        NadiAgent $agent
    ): int
    {
        return DB::transaction(function () use ($agent)
        {
            $deliveries = NadiAgentCredentialDelivery::query()
                ->where('agent_id', $agent->id)
                ->where('delivery_status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($deliveries as $delivery)
            {
                $this->cancelDelivery($delivery);
            }

            return NadiAgentCredential::query()
                ->where('agent_id', $agent->id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                ]);
        });
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateToken(): string
    {
        return 'nadi_agent_' . bin2hex(random_bytes(32));
    }

    private function nextKeyVersion(string $agentId): string
    {
        $versions = NadiAgentCredential::query()
            ->where('agent_id', $agentId)
            ->pluck('key_version');

        $latestVersion = $versions
            ->map(function ($version)
            {
                return (int) $version;
            })
            ->max() ?? 0;

        return (string) ($latestVersion + 1);
    }

    private function expireDelivery(
        NadiAgentCredentialDelivery $delivery
    ): void
    {
        $newCredential = NadiAgentCredential::query()
            ->whereKey($delivery->new_credential_id)
            ->lockForUpdate()
            ->first();

        if ($newCredential && $newCredential->status === 'active')
        {
            $newCredential->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);
        }

        $delivery->update([
            'delivery_status' => 'expired',
            'encrypted_token' => '',
        ]);
    }

    private function cancelDelivery(
        NadiAgentCredentialDelivery $delivery
    ): void
    {
        $newCredential = NadiAgentCredential::query()
            ->whereKey($delivery->new_credential_id)
            ->lockForUpdate()
            ->first();

        if ($newCredential && $newCredential->status === 'active')
        {
            $newCredential->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);
        }

        $delivery->update([
            'delivery_status' => 'cancelled',
            'encrypted_token' => '',
        ]);
    }
}
