<?php

namespace App\Services\Agent;

use App\Models\NadiApplication;
use App\Models\NadiApplicationCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ApplicationCredentialService
{
    public function issue(
        NadiApplication $application,
        ?int $lifetimeDays = null
    ): array
    {
        if ($lifetimeDays !== null && $lifetimeDays < 1)
        {
            throw new RuntimeException(
                'Credential lifetime must be at least one day.'
            );
        }

        return DB::transaction(function () use (
            $application,
            $lifetimeDays
        )
        {
            $application = NadiApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeCredential = NadiApplicationCredential::query()
                ->where('application_id', $application->id)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->where(function ($query)
                {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if ($activeCredential)
            {
                throw new RuntimeException(
                    'This application already has an active credential.'
                );
            }

            $plaintextToken = 'nadi_app_' . bin2hex(random_bytes(32));

            $credential = NadiApplicationCredential::create([
                'id' => (string) Str::uuid(),
                'application_id' => $application->id,
                'credential_hash' => hash('sha256', $plaintextToken),
                'key_version' => '1',
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => $lifetimeDays !== null
                    ? now()->addDays($lifetimeDays)
                    : null,
                'revoked_at' => null,
                'last_used_at' => null,
            ]);

            return [
                'credential' => $credential,
                'token' => $plaintextToken,
            ];
        });
    }

    public function revoke(
        NadiApplicationCredential $credential
    ): NadiApplicationCredential
    {
        return DB::transaction(function () use ($credential)
        {
            $credential = NadiApplicationCredential::query()
                ->whereKey($credential->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($credential->status === 'revoked')
            {
                return $credential;
            }

            $credential->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            return $credential->fresh();
        });
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
