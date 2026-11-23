<?php

namespace App\Http\Controllers;

use App\Models\NadiAgent;
use App\Models\NadiAgentCredential;
use App\Services\Agent\AgentCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AgentCredentialController extends Controller
{
    public function index(NadiAgent $agent)
    {
        $credentials = NadiAgentCredential::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'agent_id',
                'key_version',
                'status',
                'issued_at',
                'expires_at',
                'revoked_at',
                'last_used_at',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'agent_id' => $agent->id,
                'credentials' => $credentials,
            ],
        ]);
    }

    public function issue(
        Request $request,
        NadiAgent $agent,
        AgentCredentialService $credentialService
    ) {
        $validated = $request->validate([
            'lifetime_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
        ]);

        $lifetimeDays = $validated['lifetime_days'] ?? 90;

        try {
            $issued = $credentialService->issue(
                $agent,
                $lifetimeDays
            );

            return $this->jsonNoStore([
                'success' => true,
                'message' => 'Agent credential issued successfully.',
                'data' => [
                    'agent_id' => $agent->id,
                    'credential_id' => $issued['credential']->id,
                    'key_version' => $issued['credential']->key_version,
                    'status' => $issued['credential']->status,
                    'issued_at' => $issued['credential']->issued_at,
                    'expires_at' => $issued['credential']->expires_at,
                    'token' => $issued['token'],
                    'warning' => 'This token will not be shown again.',
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return $this->jsonNoStore([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }

    public function rotate(
        Request $request,
        NadiAgent $agent,
        AgentCredentialService $credentialService
    ) {
        $validated = $request->validate([
            'lifetime_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
        ]);

        try {
            $result = $credentialService->rotate(
                $agent,
                $validated['lifetime_days'] ?? 90,
                15
            );

            return response()->json([
                'success' => true,
                'message' => 'Credential rotation has been initiated.',
                'data' => [
                    'agent_id' => $agent->id,
                    'delivery_id' => $result['delivery']->id,
                    'old_credential_id' => $result['old_credential']->id,
                    'new_credential_id' => $result['new_credential']->id,
                    'new_key_version' => $result['new_credential']->key_version,
                    'delivery_status' => $result['delivery']->delivery_status,
                    'delivery_expires_at' => $result['delivery']->expires_at,
                    'message' => 'The old credential remains valid until the new credential is acknowledged.',
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }

    public function revoke(
        NadiAgent $agent,
        NadiAgentCredential $credential,
        AgentCredentialService $credentialService
    ) {
        if ($credential->agent_id !== $agent->id) {
            abort(404);
        }

        try {
            $revoked = $credentialService->revoke($credential);

            return response()->json([
                'success' => true,
                'message' => 'Agent credential revoked successfully.',
                'data' => [
                    'credential_id' => $revoked->id,
                    'agent_id' => $agent->id,
                    'status' => $revoked->status,
                    'revoked_at' => $revoked->revoked_at,
                ],
            ]);
        } catch (RuntimeException $exception) {
            Log::error('NADI credential revocation failed.', [
                'agent_id' => $agent->id,
                'credential_id' => $credential->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credential revocation failed.',
            ], 500);
        }
    }

    private function jsonNoStore(
        array $payload,
        int $status = 200
    ): JsonResponse {
        return response()->json($payload, $status)->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
