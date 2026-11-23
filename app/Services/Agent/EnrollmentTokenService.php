<?php

namespace App\Services\Agent;

use App\Models\NadiEnrollmentToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EnrollmentTokenService
{
    public function issue(
        ?string $tokenName,
        ?string $environmentScope,
        int $lifetimeHours,
        ?string $createdBy
    ): array
    {
        if ($lifetimeHours < 1 || $lifetimeHours > 720)
        {
            throw new RuntimeException(
                'Enrollment token lifetime must be between 1 and 720 hours.'
            );
        }

        $plaintextToken = 'nadi_enroll_' . bin2hex(random_bytes(32));

        $token = NadiEnrollmentToken::create([
            'token_hash' => hash('sha256', $plaintextToken),
            'token_name' => $tokenName,
            'environment_scope' => $environmentScope,
            'expires_at' => now()->addHours($lifetimeHours),
            'used_at' => null,
            'revoked_at' => null,
            'created_by' => $createdBy,
        ]);

        return [
            'token' => $plaintextToken,
            'record' => $token,
        ];
    }

    public function lockValidToken(string $plaintextToken): NadiEnrollmentToken
    {
        if (
            !str_starts_with($plaintextToken, 'nadi_enroll_')
            || strlen($plaintextToken) !== 76
        )
        {
            throw new RuntimeException(
                'Invalid enrollment token.'
            );
        }

        $tokenHash = hash('sha256', $plaintextToken);

        $token = NadiEnrollmentToken::query()
            ->where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->first();

        if (!$token)
        {
            throw new RuntimeException(
                'Invalid enrollment token.'
            );
        }

        if ($token->revoked_at)
        {
            throw new RuntimeException(
                'Enrollment token has been revoked.'
            );
        }

        if ($token->used_at)
        {
            throw new RuntimeException(
                'Enrollment token has already been used.'
            );
        }

        if (!$token->expires_at || $token->expires_at->isPast())
        {
            throw new RuntimeException(
                'Enrollment token has expired.'
            );
        }

        return $token;
    }

    public function markAsUsed(NadiEnrollmentToken $token): NadiEnrollmentToken
    {
        $token->update([
            'used_at' => now(),
        ]);

        return $token->fresh();
    }

    public function revoke(NadiEnrollmentToken $token): NadiEnrollmentToken
    {
        return DB::transaction(function () use ($token)
        {
            $lockedToken = NadiEnrollmentToken::query()
                ->whereKey($token->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedToken->revoked_at)
            {
                $lockedToken->update([
                    'revoked_at' => now(),
                ]);
            }

            return $lockedToken->fresh();
        });
    }
}
