<?php

namespace App\Http\Middleware;

use App\Models\NadiAgentCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NadiAgentAuthentication
{
    public function handle(
        Request $request,
        Closure $next
    ): Response
    {
        $token = $request->bearerToken();

        if (!$token || strlen($token) < 32)
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

        $credentialHash = hash('sha256', $token);

        $credential = NadiAgentCredential::query()
            ->where('credential_hash', $credentialHash)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($query)
            {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('agent')
            ->first();

        if (!$credential || !$credential->agent)
        {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked agent credential.',
            ], 401);
        }

        $agent = $credential->agent;

        if ($agent->installation_state !== 'installed')
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent installation is inactive.',
            ], 403);
        }

        $credential->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set(
            'nadi_agent',
            $agent
        );

        $request->attributes->set(
            'nadi_credential',
            $credential
        );

        return $next($request);
    }
}
