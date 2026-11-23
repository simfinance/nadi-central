<?php

namespace App\Services\Activation;

use App\Models\NadiRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GrantPayloadBuilder
{
    public function build(
        NadiRegistration $registration,
        int $grantVersion,
        string $keyId,
        int $lifetimeDays
    ): array
    {
        $application = $registration->application;
        $agent = $registration->agent;
        $server = $agent->server;

        $issuedAt = Carbon::now('UTC');
        $expiresAt = $issuedAt->copy()->addDays($lifetimeDays);

        return [
            'grant_id' => (string) Str::uuid(),
            'grant_version' => $grantVersion,
            'key_id' => $keyId,

            'registration_id' => $registration->id,
            'application_id' => $application->id,
            'agent_id' => $agent->id,
            'server_id' => $server->id,

            'issued_at' => $issuedAt->toISOString(),
            'expires_at' => $expiresAt->toISOString(),

            'capabilities' => config(
                'nadi.activation.capabilities',
                []
            ),

            'monitoring_enabled' => true,
        ];
    }
}
