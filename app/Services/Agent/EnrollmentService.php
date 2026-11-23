<?php

namespace App\Services\Agent;

use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Models\NadiApplicationEndpoint;
use App\Models\NadiEnrollmentToken;
use App\Models\NadiRegistration;
use App\Models\NadiServer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class EnrollmentService
{
    public function __construct(
        private AgentCredentialService $agentCredentialService,
        private ApplicationCredentialService $applicationCredentialService
    )
    {
    }

    public function enroll(array $data): array
    {
        $payload = $data;

        unset($payload['enrollment_token']);

        $payloadHash = hash(
            'sha256',
            json_encode(
                $this->normalizePayload($payload),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        try
        {
            return DB::transaction(function () use (
                $data,
                $payloadHash
            )
            {
                $tokenHash = hash(
                    'sha256',
                    $data['enrollment_token']
                );

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
                    return $this->replayEnrollment(
                        $token,
                        $data,
                        $payloadHash
                    );
                }

                if (
                    !$token->expires_at
                    || $token->expires_at->isPast()
                )
                {
                    throw new RuntimeException(
                        'Enrollment token has expired.'
                    );
                }

                $this->validateEnvironmentScope(
                    $token,
                    $data['applications']
                );

                $this->validateApplicationUrls(
                    $data['applications']
                );

                $existingAgent = NadiAgent::query()
                    ->where(
                        'agent_instance_id',
                        $data['agent']['agent_instance_id']
                    )
                    ->exists();

                if ($existingAgent)
                {
                    throw new RuntimeException(
                        'This Agent instance is already registered.'
                    );
                }

                $this->validateApplicationKeys(
                    $data['applications']
                );

                $server = NadiServer::create([
                    'hostname' => $data['server']['hostname'],
                    'ip_address' => $data['server']['ip_address'] ?? null,
                    'os_name' => $data['server']['os_name'] ?? null,
                    'os_version' => $data['server']['os_version'] ?? null,
                    'architecture' => $data['server']['architecture'] ?? null,
                    'status' => 'registered',
                ]);

                $agent = NadiAgent::create([
                    'server_id' => $server->id,
                    'agent_instance_id' => $data['agent']['agent_instance_id'],
                    'agent_version' => $data['agent']['agent_version'],
                    'protocol_version' => $data['agent']['protocol_version'],
                    'installation_state' => 'installed',
                    'status' => 'registered',
                    'installed_at' => now(),
                ]);

                $agentCredential = $this->agentCredentialService->issue(
                    $agent,
                    90
                );

                $applicationResponses = [];

                foreach ($data['applications'] as $applicationData)
                {
                    $application = NadiApplication::create([
                        'agent_id' => $agent->id,
                        'application_name' => $applicationData['application_name'],
                        'application_key' => $applicationData['application_key'],
                        'environment' => $applicationData['environment'],
                        'framework' => 'laravel',
                        'framework_version' => $applicationData['framework_version'] ?? null,
                        'php_version' => $applicationData['php_version'] ?? null,
                        'registration_status' => 'pending',
                        'activation_status' => 'pending',
                        'monitoring_status' => 'dormant',
                        'registered_at' => now(),
                    ]);

                    $endpoint = NadiApplicationEndpoint::create([
                        'application_id' => $application->id,
                        'base_url' => rtrim(
                            $applicationData['base_url'],
                            '/'
                        ),
                        'endpoint_prefix' => '/_nadi/v1',
                        'verification_status' => 'pending',
                    ]);

                    $registration = NadiRegistration::create([
                        'application_id' => $application->id,
                        'agent_id' => $agent->id,
                        'registration_status' => 'pending',
                        'requested_at' => now(),
                    ]);

                    $applicationCredential = $this
                        ->applicationCredentialService
                        ->issue($application);

                    $applicationResponses[] = [
                        'application_id' => $application->id,
                        'application_key' => $application->application_key,
                        'application_name' => $application->application_name,
                        'environment' => $application->environment,
                        'registration_id' => $registration->id,
                        'registration_status' => 'pending',
                        'activation_status' => 'pending',
                        'monitoring_status' => 'dormant',
                        'endpoint' => $endpoint->base_url
                            . $endpoint->endpoint_prefix,
                        'application_credential' => $applicationCredential['token'],
                    ];
                }

                $response = [
                    'success' => true,
                    'message' => 'Enrollment completed. Waiting for admin approval.',
                    'data' => [
                        'server_id' => $server->id,
                        'agent_id' => $agent->id,
                        'agent_instance_id' => $agent->agent_instance_id,
                        'agent_status' => 'registered',
                        'agent_credential' => $agentCredential['token'],
                        'agent_credential_expires_at' => $agentCredential['credential']
                            ->expires_at
                            ->toIso8601String(),
                        'control' => [
                            'poll_url' => url(
                                '/api/v1/nadi/agent/control/poll'
                            ),
                            'poll_interval_seconds' => 30,
                        ],
                        'applications' => $applicationResponses,
                    ],
                ];

                $token->update([
                    'used_at' => now(),
                    'enrollment_request_id' => $data['request_id'],
                    'used_by_agent_instance_id' => $agent->agent_instance_id,
                    'enrollment_payload_hash' => $payloadHash,
                    'response_encrypted' => Crypt::encryptString(
                        json_encode(
                            $response,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        )
                    ),
                ]);

                return [
                    'response' => $response,
                    'replayed' => false,
                ];
            }, 3);
        }
        catch (QueryException $exception)
        {
            if ($this->isUniqueConstraintViolation($exception))
            {
                throw new RuntimeException(
                    'Enrollment conflicts with an existing Agent or Application identity.'
                );
            }

            throw $exception;
        }
    }

    private function replayEnrollment(
        NadiEnrollmentToken $token,
        array $data,
        string $payloadHash
    ): array
    {
        $sameRequest = $token->enrollment_request_id
            === $data['request_id'];

        $sameAgent = $token->used_by_agent_instance_id
            === $data['agent']['agent_instance_id'];

        $samePayload = $token->enrollment_payload_hash
            === $payloadHash;

        if (!$sameRequest || !$sameAgent || !$samePayload)
        {
            throw new RuntimeException(
                'Enrollment token has already been used by another request.'
            );
        }

        if (!$token->response_encrypted)
        {
            throw new RuntimeException(
                'Enrollment response is unavailable.'
            );
        }

        try
        {
            $response = json_decode(
                Crypt::decryptString($token->response_encrypted),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }
        catch (Throwable $exception)
        {
            throw new RuntimeException(
                'Unable to recover the original enrollment response.'
            );
        }

        return [
            'response' => $response,
            'replayed' => true,
        ];
    }

    private function validateEnvironmentScope(
        NadiEnrollmentToken $token,
        array $applications
    ): void
    {
        if (!$token->environment_scope)
        {
            return;
        }

        foreach ($applications as $application)
        {
            if (
                $application['environment']
                !== $token->environment_scope
            )
            {
                throw new RuntimeException(
                    'Application environment does not match enrollment token scope.'
                );
            }
        }
    }

    private function validateApplicationUrls(
        array $applications
    ): void
    {
        foreach ($applications as $application)
        {
            $scheme = parse_url(
                $application['base_url'],
                PHP_URL_SCHEME
            );

            if (strtolower((string) $scheme) !== 'https')
            {
                throw new RuntimeException(
                    'Application endpoint must use HTTPS.'
                );
            }
        }
    }

    private function validateApplicationKeys(
        array $applications
    ): void
    {
        $keys = array_column(
            $applications,
            'application_key'
        );

        if (count($keys) !== count(array_unique($keys)))
        {
            throw new RuntimeException(
                'Duplicate application_key values were submitted.'
            );
        }

        $existingKeys = NadiApplication::query()
            ->whereIn('application_key', $keys)
            ->exists();

        if ($existingKeys)
        {
            throw new RuntimeException(
                'An application with this application_key already exists.'
            );
        }
    }

    
    private function isUniqueConstraintViolation(
        QueryException $exception
    ): bool
    {
        return isset($exception->errorInfo[1])
            && (int) $exception->errorInfo[1] === 1062;
    }


    private function normalizePayload(array $payload): array
    {
        foreach ($payload as &$value)
        {
            if (is_array($value))
            {
                $value = $this->normalizePayload($value);
            }
        }

        unset($value);

        if (!array_is_list($payload))
        {
            ksort($payload);
        }

        return $payload;
    }
}