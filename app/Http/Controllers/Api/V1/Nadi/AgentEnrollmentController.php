<?php

namespace App\Http\Controllers\Api\V1\Nadi;

use App\Http\Controllers\Controller;
use App\Services\Agent\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AgentEnrollmentController extends Controller
{
    public function enroll(
        Request $request,
        EnrollmentService $enrollmentService
    ): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_token' => [
                'required',
                'string',
                'min:76',
                'max:76',
            ],

            'request_id' => [
                'required',
                'uuid',
            ],

            'server' => [
                'required',
                'array',
            ],

            'server.hostname' => [
                'required',
                'string',
                'max:255',
            ],

            'server.ip_address' => [
                'nullable',
                'ip',
            ],

            'server.os_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'server.os_version' => [
                'nullable',
                'string',
                'max:100',
            ],

            'server.architecture' => [
                'nullable',
                'string',
                'max:30',
            ],

            'agent' => [
                'required',
                'array',
            ],

            'agent.agent_instance_id' => [
                'required',
                'uuid',
            ],

            'agent.agent_version' => [
                'required',
                'string',
                'max:50',
            ],

            'agent.protocol_version' => [
                'required',
                'string',
                'max:30',
            ],

            'applications' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'applications.*.application_key' => [
                'required',
                'string',
                'max:100',
                'distinct',
            ],

            'applications.*.application_name' => [
                'required',
                'string',
                'max:255',
            ],

            'applications.*.environment' => [
                'required',
                'string',
                'max:50',
            ],

            'applications.*.framework_version' => [
                'nullable',
                'string',
                'max:50',
            ],

            'applications.*.php_version' => [
                'nullable',
                'string',
                'max:30',
            ],

            'applications.*.base_url' => [
                'required',
                'url',
                'max:2048',
            ],
        ]);

        try
        {
            $result = $enrollmentService->enroll($validated);

            return response()->json(
                $result['response'],
                $result['replayed'] ? 200 : 201
            );
        }
        catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }
}
