<?php

namespace App\\Http\\Controllers\\Api\\V1\\Nadi;

use App\\Http\\Controllers\\Controller;
use App\\Models\\NadiActivationGrant;
use App\\Models\\NadiAgentCommand;
use App\\Services\\Activation\\GrantSigner;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

class AgentControlController extends Controller
{
    public function __construct(
        protected GrantSigner $signer
    )
    {
    }

    public function poll(Request $request)
    {
        $agent = $request->attributes->get('nadi_agent');

        if (!$agent)
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

        $result = DB::transaction(function () use ($agent)
        {
            $agent->refresh();

            $agent->update([
                'last_control_at' => now(),
            ]);

            $applications = $agent->applications()
                ->with('endpoint')
                ->get();

            $agentCanMonitor = $agent->installation_state === 'installed';

            $applicationIds = $applications->pluck('id');

            $grants = NadiActivationGrant::query()
                ->with('registration')
                ->where('agent_id', $agent->id)
                ->whereIn('application_id', $applicationIds)
                ->where('grant_status', 'active')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->orderByDesc('grant_version')
                ->orderByDesc('issued_at')
                ->get()
                ->groupBy('application_id');

            $applicationData = $applications->map(function ($application) use (
                $grants,
                $agent,
                $agentCanMonitor
            )
            {
                $grant = null;

                $applicationCanMonitor = (
                    $agentCanMonitor
                    && $application->registration_status === 'approved'
                    && in_array(
                        $application->activation_status,
                        ['pending', 'active'],
                        true
                    )
                    && $application->monitoring_suspended_at === null
                );

                if ($applicationCanMonitor)
                {
                    $applicationGrants = $grants->get(
                        $application->id,
                        collect()
                    );

                    foreach ($applicationGrants as $candidate)
                    {
                        if ($this->isGrantEligible(
                            $candidate,
                            $application,
                            $agent
                        ))
                        {
                            $grant = $candidate;

                            break;
                        }
                    }
                }

                return [
                    'application_id' => $application->id,
                    'name' => $application->application_name,
                    'registration_status' => $application->registration_status,
                    'activation_status' => $application->activation_status,
                    'monitoring_status' => $application->monitoring_status,
                    'endpoint' => $application->endpoint?->base_url,
                    'monitoring_enabled' => (bool) $grant,
                    'activation_grant' => $grant ? [
                        'grant_id' => $grant->id,
                        'grant_version' => $grant->grant_version,
                        'key_id' => $grant->key_id,
                        'payload' => $grant->grant_payload,
                        'signature' => $grant->signature,
                        'expires_at' => $grant->expires_at,
                    ] : null,
                ];
            });

            $retryInterval = max(
                5,
                (int) config(
                    'nadi.agent_commands.delivery_retry_seconds',
                    60
                )
            );

            $retryBefore = now()->subSeconds($retryInterval);

            $commands = NadiAgentCommand::query()
                ->where('agent_id', $agent->id)
                ->where(function ($query) use ($retryBefore)
                {
                    $query->where('command_status', 'pending')
                        ->orWhere(function ($retryQuery) use ($retryBefore)
                        {
                            $retryQuery->where('command_status', 'delivered')
                                ->whereNull('acknowledged_at')
                                ->where(function ($deliveryQuery) use ($retryBefore)
                                {
                                    $deliveryQuery->whereNull('delivered_at')
                                        ->orWhere('delivered_at', '<=', $retryBefore);
                                });
                        });
                })
                ->where(function ($query)
                {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderBy('issued_at')
                ->lockForUpdate()
                ->get();

            foreach ($commands as $command)
            {
                $command->update([
                    'command_status' => 'delivered',
                    'delivered_at' => now(),
                    'delivery_attempts' => ((int) $command->delivery_attempts) + 1,
                ]);
            }

            return [
                'agent' => [
                    'id' => $agent->id,
                    'agent_instance_id' => $agent->agent_instance_id,
                    'status' => $agent->status,
                    'installation_state' => $agent->installation_state,
                    'agent_version' => $agent->agent_version,
                    'protocol_version' => $agent->protocol_version,
                ],
                'control' => [
                    'poll_interval_seconds' => 30,
                ],
                'applications' => $applicationData,
                'commands' => $commands->map(function ($command)
                {
                    return [
                        'command_id' => $command->id,
                        'application_id' => $command->application_id,
                        'command_type' => $command->command_type,
                        'payload' => $command->command_payload,
                        'issued_at' => $command->issued_at,
                        'expires_at' => $command->expires_at,
                    ];
                }),
                'server_time' => now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function acknowledge(Request $request, string $commandId)
    {
        $agent = $request->attributes->get('nadi_agent');

        if (!$agent)
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

        $result = DB::transaction(function () use ($agent, $commandId)
        {
            $command = NadiAgentCommand::query()
                ->where('agent_id', $agent->id)
                ->whereKey($commandId)
                ->lockForUpdate()
                ->first();

            if (!$command)
            {
                return [
                    'http_status' => 404,
                    'body' => [
                        'success' => false,
                        'message' => 'Command not found.',
                    ],
                ];
            }

            if ($command->acknowledged_at !== null)
            {
                return [
                    'http_status' => 200,
                    'body' => [
                        'success' => true,
                        'acknowledged' => true,
                        'idempotent' => true,
                        'command_id' => $command->id,
                        'acknowledged_at' => $command->acknowledged_at,
                    ],
                ];
            }

            if (
                $command->expires_at !== null
                && $command->expires_at->isPast()
            )
            {
                return [
                    'http_status' => 410,
                    'body' => [
                        'success' => false,
                        'message' => 'Command has expired.',
                    ],
                ];
            }

            if ($command->command_status !== 'delivered')
            {
                return [
                    'http_status' => 409,
                    'body' => [
                        'success' => false,
                        'message' => 'Command has not been delivered.',
                    ],
                ];
            }

            $command->update([
                'acknowledged_at' => now(),
            ]);

            return [
                'http_status' => 200,
                'body' => [
                    'success' => true,
                    'acknowledged' => true,
                    'idempotent' => false,
                    'command_id' => $command->id,
                    'acknowledged_at' => $command->fresh()->acknowledged_at,
                ],
            ];
        });

        return response()->json(
            $result['body'],
            $result['http_status']
        );
    }

    private function isGrantEligible(
        NadiActivationGrant $grant,
        $application,
        $agent
    ): bool
    {
        if ($grant->grant_status !== 'active')
        {
            return false;
        }

        if ($grant->acknowledgement_status === 'failed')
        {
            return false;
        }

        if ($grant->revoked_at !== null)
        {
            return false;
        }

        if (!$grant->expires_at || !$grant->expires_at->isFuture())
        {
            return false;
        }

        if (
            $grant->application_id !== $application->id
            || $grant->agent_id !== $agent->id
        )
        {
            return false;
        }

        $payload = $grant->grant_payload;

        if (!is_array($payload))
        {
            return false;
        }

        $registration = $grant->registration;

        if (!$registration)
        {
            return false;
        }

        if (
            $registration->registration_status !== 'approved'
            || $registration->application_id !== $application->id
            || $registration->agent_id !== $agent->id
        )
        {
            return false;
        }

        if (
            ($payload['grant_id'] ?? null) !== $grant->id
            || ($payload['registration_id'] ?? null) !== $registration->id
            || ($payload['application_id'] ?? null) !== $application->id
            || ($payload['agent_id'] ?? null) !== $agent->id
            || ($payload['server_id'] ?? null) !== $agent->server_id
            || (int) ($payload['grant_version'] ?? 0) !== (int) $grant->grant_version
            || ($payload['key_id'] ?? null) !== $grant->key_id
            || ($payload['monitoring_enabled'] ?? false) !== true
        )
        {
            return false;
        }

        try
        {
            return $this->signer->verify(
                $payload,
                $grant->signature
            );
        }
        catch (Throwable $exception)
        {
            report($exception);

            return false;
        }
    }
}
