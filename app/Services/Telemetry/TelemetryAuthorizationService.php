<?php

namespace App\Services\Telemetry;

use App\Models\NadiActivationGrant;
use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Services\Activation\GrantSigner;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TelemetryAuthorizationService
{
    private const STREAM_CAPABILITIES = [
        'server_metrics' => 'server_metrics',
        'nginx_metrics' => 'server_metrics',
        'nginx_vhost_metrics' => 'server_metrics',
        'nginx_error_logs' => 'system_logs',
        'application_metrics' => 'application_metrics',
        'application_logs' => 'application_logs',
        'database_metrics' => 'database_monitoring',
        'database_connections' => 'database_monitoring',
        'queue_metrics' => 'queue_monitoring',
        'queue_jobs' => 'queue_monitoring',
        'scheduler_metrics' => 'scheduler_monitoring',
        'scheduler_events' => 'scheduler_monitoring',
    ];

    private const SERVER_STREAMS = [
        'server_metrics',
        'nginx_metrics',
        'nginx_vhost_metrics',
        'nginx_access_logs',
        'nginx_error_logs',
    ];

    private const LOG_STREAMS = [
        'nginx_error_logs',
        'application_logs',
    ];

    private const LOG_LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    private const SENSITIVE_KEYS = [
        'authorization',
        'proxy_authorization',
        'password',
        'passwd',
        'token',
        'access_token',
        'refresh_token',
        'id_token',
        'cookie',
        'set_cookie',
        'session',
        'secret',
        'api_key',
        'apikey',
        'private_key',
        'client_secret',
        'credential',
        'stack_trace',
        'trace',
    ];

    public function __construct(
        protected GrantSigner $signer
    )
    {
    }

    public function authorizeAndSanitize(
        array $payload,
        NadiAgent $agent
    ): array
    {
        $applications = [];

        foreach ($payload['records'] as $index => $record)
        {
            $stream = $record['stream'] ?? null;
            $capability = self::STREAM_CAPABILITIES[$stream] ?? null;

            if (!$capability)
            {
                throw new HttpException(
                    422,
                    'Telemetry stream has no approved capability mapping.'
                );
            }

            $applicationId = $record['application_id'] ?? null;

            if (in_array($stream, self::SERVER_STREAMS, true))
            {
                if ($applicationId !== null)
                {
                    throw new HttpException(
                        422,
                        'Server-scoped telemetry must not include application_id.'
                    );
                }

                $this->authorizeServerScope(
                    $agent,
                    $capability
                );
            }
            else
            {
                if (!is_string($applicationId) || $applicationId === '')
                {
                    throw new HttpException(
                        422,
                        'Application-scoped telemetry requires application_id.'
                    );
                }

                $application = $applications[$applicationId]
                    ?? NadiApplication::query()
                        ->where('id', $applicationId)
                        ->where('agent_id', $agent->id)
                        ->first();

                if (!$application)
                {
                    throw new HttpException(
                        403,
                        'Application does not belong to this Agent.'
                    );
                }

                $applications[$applicationId] = $application;

                if (!$this->isApplicationEligible($application, $agent))
                {
                    throw new HttpException(
                        410,
                        'Application monitoring scope is no longer active.'
                    );
                }

                if (!$this->hasAcknowledgedGrant(
                    $application,
                    $agent,
                    $capability
                ))
                {
                    throw new HttpException(
                        410,
                        'No valid acknowledged activation grant authorizes this telemetry scope.'
                    );
                }
            }

            if (in_array($stream, self::LOG_STREAMS, true))
            {
                if (!config('nadi.telemetry.logs_enabled', false))
                {
                    throw new HttpException(
                        503,
                        'Log telemetry ingestion is disabled pending Agent contract verification.'
                    );
                }

                $payload['records'][$index]['data'] = $this->validateAndSanitizeLog(
                    $stream,
                    $record['data'] ?? null
                );
            }
        }

        return $payload;
    }

    private function authorizeServerScope(
        NadiAgent $agent,
        string $capability
    ): void
    {
        $applications = NadiApplication::query()
            ->where('agent_id', $agent->id)
            ->where('registration_status', 'approved')
            ->where('activation_status', 'active')
            ->whereNull('monitoring_suspended_at')
            ->get();

        foreach ($applications as $application)
        {
            if ($this->hasAcknowledgedGrant(
                $application,
                $agent,
                $capability
            ))
            {
                return;
            }
        }

        throw new HttpException(
            410,
            'Server telemetry requires an active application grant explicitly carrying the required server capability.'
        );
    }

    private function isApplicationEligible(
        NadiApplication $application,
        NadiAgent $agent
    ): bool
    {
        return $application->agent_id === $agent->id
            && $application->registration_status === 'approved'
            && $application->activation_status === 'active'
            && $application->monitoring_suspended_at === null;
    }

    private function hasAcknowledgedGrant(
        NadiApplication $application,
        NadiAgent $agent,
        string $capability
    ): bool
    {
        $grants = NadiActivationGrant::query()
            ->with('registration')
            ->where('agent_id', $agent->id)
            ->where('application_id', $application->id)
            ->where('grant_status', 'active')
            ->where('acknowledgement_status', 'success')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->orderByDesc('grant_version')
            ->get();

        foreach ($grants as $grant)
        {
            if (
                !$grant->expires_at
                || !$grant->expires_at->isFuture()
                || !$grant->registration
                || $grant->registration->registration_status !== 'approved'
                || $grant->registration->application_id !== $application->id
                || $grant->registration->agent_id !== $agent->id
            )
            {
                continue;
            }

            $grantPayload = $grant->grant_payload;

            if (!is_array($grantPayload))
            {
                continue;
            }

            $capabilities = $grantPayload['capabilities'] ?? [];

            if (
                !is_array($capabilities)
                || !in_array($capability, $capabilities, true)
                || ($grantPayload['grant_id'] ?? null) !== $grant->id
                || ($grantPayload['registration_id'] ?? null) !== $grant->registration_id
                || ($grantPayload['application_id'] ?? null) !== $application->id
                || ($grantPayload['agent_id'] ?? null) !== $agent->id
                || ($grantPayload['server_id'] ?? null) !== $agent->server_id
                || (int) ($grantPayload['grant_version'] ?? 0) !== (int) $grant->grant_version
                || ($grantPayload['key_id'] ?? null) !== $grant->key_id
                || ($grantPayload['monitoring_enabled'] ?? false) !== true
            )
            {
                continue;
            }

            try
            {
                if ($this->signer->verify(
                    $grantPayload,
                    $grant->signature
                ))
                {
                    return true;
                }
            }
            catch (Throwable $exception)
            {
                report($exception);
            }
        }

        return false;
    }

    private function validateAndSanitizeLog(
        string $stream,
        mixed $data
    ): array
    {
        if (!is_array($data))
        {
            throw new HttpException(
                422,
                'Log record data must be an object.'
            );
        }

        if ($stream === 'nginx_error_logs')
        {
            $allowed = ['timestamp', 'severity', 'message', 'source'];

            $this->assertAllowedKeys($data, $allowed);

            $severity = $data['severity'] ?? null;

            if (
                !is_string($severity)
                || !in_array(strtolower($severity), self::LOG_LEVELS, true)
            )
            {
                throw new HttpException(
                    422,
                    'nginx_error_logs severity is invalid.'
                );
            }

            $source = $data['source'] ?? 'nginx';

            if (!is_string($source) || $source !== 'nginx')
            {
                throw new HttpException(
                    422,
                    'nginx_error_logs source must be nginx.'
                );
            }

            return [
                'timestamp' => $this->validateLogTimestamp($data['timestamp'] ?? null),
                'severity' => strtolower($severity),
                'source' => 'nginx',
                'message' => $this->sanitizeLogMessage(
                    $data['message'] ?? null,
                    8192
                ),
            ];
        }

        $allowed = ['timestamp', 'level', 'channel', 'message', 'context'];

        $this->assertAllowedKeys($data, $allowed);

        $level = $data['level'] ?? null;
        $channel = $data['channel'] ?? null;

        if (
            !is_string($level)
            || !in_array(strtolower($level), self::LOG_LEVELS, true)
        )
        {
            throw new HttpException(
                422,
                'application_logs level is invalid.'
            );
        }

        if (
            !is_string($channel)
            || $channel === ''
            || strlen($channel) > 100
        )
        {
            throw new HttpException(
                422,
                'application_logs channel is invalid.'
            );
        }

        $context = $data['context'] ?? [];

        if (!is_array($context) || count($context) > 50)
        {
            throw new HttpException(
                422,
                'application_logs context must be an object with at most 50 fields.'
            );
        }

        return [
            'timestamp' => $this->validateLogTimestamp($data['timestamp'] ?? null),
            'level' => strtolower($level),
            'channel' => $channel,
            'message' => $this->sanitizeLogMessage(
                $data['message'] ?? null,
                8192
            ),
            'context' => $this->sanitizeContext($context),
        ];
    }

    private function assertAllowedKeys(
        array $data,
        array $allowed
    ): void
    {
        $unknown = array_diff(array_keys($data), $allowed);

        if ($unknown)
        {
            throw new HttpException(
                422,
                'Log record contains unsupported fields.'
            );
        }
    }

    private function validateLogTimestamp(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '')
        {
            throw new HttpException(
                422,
                'Log timestamp is required.'
            );
        }

        try
        {
            return CarbonImmutable::parse($value)
                ->utc()
                ->toISOString();
        }
        catch (Throwable $exception)
        {
            throw new HttpException(
                422,
                'Log timestamp is invalid.'
            );
        }
    }

    private function sanitizeLogMessage(
        mixed $message,
        int $maxLength
    ): string
    {
        if (
            !is_string($message)
            || trim($message) === ''
            || strlen($message) > $maxLength
        )
        {
            throw new HttpException(
                422,
                'Log message must be a non-empty string within the size limit.'
            );
        }

        if (preg_match('/(?:stack trace:|traceback \(most recent call last\)|^#\\d+\\s)/im', $message))
        {
            throw new HttpException(
                422,
                'Raw stack traces are not accepted in log messages.'
            );
        }

        return $this->redactSensitiveText($message);
    }

    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value)
        {
            if (!is_string($key) && !is_int($key))
            {
                continue;
            }

            $normalizedKey = strtolower(
                str_replace('-', '_', (string) $key)
            );

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true))
            {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value))
            {
                $sanitized[$key] = $this->sanitizeContext($value);
            }
            elseif (is_string($value))
            {
                $sanitized[$key] = $this->redactSensitiveText(
                    mb_substr($value, 0, 2048)
                );
            }
            elseif (is_scalar($value) || $value === null)
            {
                $sanitized[$key] = $value;
            }
            else
            {
                throw new HttpException(
                    422,
                    'Log context contains an unsupported value.'
                );
            }
        }

        return $sanitized;
    }

    private function redactSensitiveText(string $value): string
    {
        $patterns = [
            '/(authorization\s*[:=]\s*)(?:Bearer\s+)?([^\s,;]+)/i',
            '/(proxy-authorization\s*[:=]\s*)(?:Bearer\s+)?([^\s,;]+)/i',
            '/(cookie\s*[:=]\s*)([^\r\n]+)/i',
            '/(set-cookie\s*[:=]\s*)([^\r\n]+)/i',
            '/((?:access|refresh|id)_token|api[_-]?key|client_secret|password|passwd|secret)\s*[:=]\s*([^\s,;]+)/i',
            '/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i',
        ];

        $replacements = [
            '$1[REDACTED]',
            '$1[REDACTED]',
            '$1[REDACTED]',
            '$1[REDACTED]',
            '$1=[REDACTED]',
            'Bearer [REDACTED]',
        ];

        return preg_replace(
            $patterns,
            $replacements,
            $value
        ) ?? '[REDACTED]';
    }
}
