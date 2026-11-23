<?php

namespace App\Http\Controllers;

use App\Models\NadiAgent;
use App\Models\NadiAgentCredential;
use App\Services\Agent\AgentCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentCredentialDeliveryController extends Controller
{
    public function retrieve(
        Request $request,
        string $deliveryId,
        AgentCredentialService $credentialService
    ) {
        $agent = $request->attributes->get('nadi_agent');

        $credential = $request->attributes->get('nadi_credential');

        if (
            !$agent instanceof NadiAgent ||
            !$credential instanceof NadiAgentCredential
        ) {
            return $this->jsonNoStore([
                'success' => false,
                'message' => 'Agent authentication context is invalid.',
            ], 401);
        }

        try {
            $delivery = $credentialService->retrievePendingDelivery(
                $agent,
                $credential,
                $deliveryId
            );

            return $this->jsonNoStore([
                'success' => true,
                'message' => 'Credential delivery retrieved successfully.',
                'data' => $delivery,
            ]);
        } catch (RuntimeException $exception) {
            return $this->jsonNoStore([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }

    public function acknowledge(
        Request $request,
        string $deliveryId,
        AgentCredentialService $credentialService
    ) {
        $agent = $request->attributes->get('nadi_agent');

        $credential = $request->attributes->get('nadi_credential');

        if (
            !$agent instanceof NadiAgent ||
            !$credential instanceof NadiAgentCredential
        ) {
            return $this->jsonNoStore([
                'success' => false,
                'message' => 'Agent authentication context is invalid.',
            ], 401);
        }

        try {
            $newCredential = $credentialService->acknowledgeRotation(
                $agent,
                $credential,
                $deliveryId
            );

            return $this->jsonNoStore([
                'success' => true,
                'message' => 'Credential rotation acknowledged successfully.',
                'data' => [
                    'agent_id' => $agent->id,
                    'credential_id' => $newCredential->id,
                    'key_version' => $newCredential->key_version,
                    'status' => $newCredential->status,
                    'expires_at' => $newCredential->expires_at,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return $this->jsonNoStore([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
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
