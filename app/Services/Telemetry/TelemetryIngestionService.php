<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Carbon;
use App\Models\NadiActivationGrant;
use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Services\Activation\GrantSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TelemetryIngestionService
{
    private const MAX_BATCH_BYTES = 1048576;
    private const MAX_RECORDS_PER_BATCH = 500;
    private const MAX_RECORD_BYTES = 65536;

    private const STREAMS = [
        'server_metrics',
        'nginx_metrics',
        'nginx_vhost_metrics',
        'nginx_access_logs',
        'nginx_error_logs',
        'application_metrics',
        'application_logs',
        'application_events',
        'nginx_http_requests',
        'application_http_requests',
        'application_http_errors',
        'database_metrics',
        'database_connections',
        'queue_metrics',
        'queue_jobs',
        'scheduler_metrics',
        'scheduler_events',
    ];

    public function __construct(
        protected GrantSigner $signer
    )
    {
    }

    public function ingest(
        array $payload,
        NadiAgent $agent
    ): array
    {
        if (
            ($payload['agent_instance_id'] ?? null)
            !== $agent->agent_instance_id
        )
        {
            throw new HttpException(
                403,
                'Agent instance identity mismatch.'
            );
        }

        if ($agent->installation_state !== 'installed')
        {
            throw new HttpException(
                403,
                'Agent installation is inactive.'
            );
        }

        $this->validatePayloadLimits($payload);

        $payloadHash = hash(
            'sha256',
            $this->canonicalJson($payload)
        );

        return DB::transaction(function () use (
            $payload,
            $agent,
            $payloadHash
        )
        {
            /*
             * Serialize telemetry ingestion per Agent.
             * This protects batch and sequence processing
             * from concurrent requests by the same Agent.
             */

            DB::table('nadi_agents')
                ->where('id', $agent->id)
                ->lockForUpdate()
                ->first();

            $existingBatch = DB::table('nadi_telemetry_batches')
                ->where('agent_id', $agent->id)
                ->where('batch_id', $payload['batch_id'])
                ->lockForUpdate()
                ->first();

            if ($existingBatch)
            {
                return $this->replayExistingBatch(
                    $existingBatch,
                    $payload
                );
            }

            $this->validateBatchAge(
                $payload['sent_at'] ?? null
            );

            $batchInternalId = (string) Str::uuid();

            DB::table('nadi_telemetry_batches')->insert([
                'id' => $batchInternalId,
                'agent_id' => $agent->id,
                'batch_id' => $payload['batch_id'],
                'protocol_version' => $payload['protocol_version'],
                'payload_hash' => $payloadHash,
                'sent_at' => $this->toUtcDateTime($payload['sent_at']),
                'received_at' => now('UTC')->format('Y-m-d H:i:s.u'),
                'batch_status' => 'processing',
                'record_count' => count($payload['records']),
                'created_at' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            $applicationCache = [];

            $acceptedCount = 0;
            $duplicateCount = 0;

            foreach ($payload['records'] as $record)
            {
                $streamName = $record['stream'];

                if (!in_array($streamName, self::STREAMS, true))
                {
                    throw new HttpException(
                        422,
                        'Unsupported telemetry stream: ' . $streamName
                    );
                }

                $applicationId = $record['application_id'] ?? null;

                $this->validateApplicationScope(
                    $streamName,
                    $applicationId,
                    $agent,
                    $applicationCache
                );

                $streamId = $this->resolveStream(
                    $agent,
                    $applicationId,
                    $streamName
                );

                $recordHash = hash(
                    'sha256',
                    $this->canonicalJson($record)
                );

                $existingRecord = DB::table('nadi_telemetry_record_keys')
                    ->where('agent_id', $agent->id)
                    ->where('record_id', $record['record_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingRecord)
                {
                    if (
                        !hash_equals(
                            $existingRecord->payload_hash,
                            $recordHash
                        )
                        || $existingRecord->stream_id !== $streamId
                        || (int) $existingRecord->sequence
                            !== (int) $record['sequence']
                    )
                    {
                        throw new HttpException(
                            409,
                            'Telemetry record ID conflicts with an existing record.'
                        );
                    }

                    DB::table('nadi_telemetry_record_keys')
                        ->where('id', $existingRecord->id)
                        ->update([
                            'last_seen_at' => now('UTC')
                                ->format('Y-m-d H:i:s.u'),
                        ]);

                    $duplicateCount++;

                    continue;
                }

                $existingSequence = DB::table('nadi_telemetry_record_keys')
                    ->where('stream_id', $streamId)
                    ->where('sequence', $record['sequence'])
                    ->lockForUpdate()
                    ->first();

                if ($existingSequence)
                {
                    throw new HttpException(
                        409,
                        'Telemetry sequence already belongs to another record.'
                    );
                }

                DB::table('nadi_telemetry_record_keys')->insert([
                    'id' => (string) Str::uuid(),
                    'agent_id' => $agent->id,
                    'stream_id' => $streamId,
                    'record_id' => $record['record_id'],
                    'sequence' => $record['sequence'],
                    'payload_hash' => $recordHash,
                    'first_seen_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                    'last_seen_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                    'created_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                ]);

                if ($this->isHttpStream($streamName))
                {
                    $this->storeHttpEvent(
                        $record,
                        $batchInternalId,
                        $streamId,
                        $agent
                    );
                }
                else
                {
                    $this->storeTelemetryRecord(
                        $record,
                        $batchInternalId,
                        $streamId,
                        $agent
                    );
                }

                $this->updateSequence(
                    $streamId,
                    $agent,
                    $record
                );

                $acceptedCount++;
            }

            $ack = [
                'success' => true,
                'batch_id' => $payload['batch_id'],
                'status' => 'accepted',
                'record_count' => count($payload['records']),
                'accepted_count' => $acceptedCount,
                'duplicate_count' => $duplicateCount,
                'rejected_count' => 0,
                'acknowledged_at' => now('UTC')->toIso8601String(),
                'replayed' => false,
            ];

            DB::table('nadi_telemetry_batches')
                ->where('id', $batchInternalId)
                ->update([
                    'batch_status' => 'accepted',
                    'accepted_count' => $acceptedCount,
                    'duplicate_count' => $duplicateCount,
                    'rejected_count' => 0,
                    'ack_response' => json_encode(
                        $ack,
                        JSON_THROW_ON_ERROR
                    ),
                ]);

            return $ack;
        }, 3);
    }
    
    private function validatePayloadLimits(array $payload): void
    {
        $records = $payload['records'] ?? null;

        if (!is_array($records) || !array_is_list($records))
        {
            throw new HttpException(
                422,
                'Telemetry records must be a valid list.'
            );
        }

        if (count($records) > self::MAX_RECORDS_PER_BATCH)
        {
            throw new HttpException(
                413,
                'Telemetry batch exceeds the maximum record count.'
            );
        }

        try
        {
            $batchJson = $this->canonicalJson($payload);
        }
        catch (Throwable $exception)
        {
            throw new HttpException(
                422,
                'Telemetry batch contains invalid JSON data.'
            );
        }

        if (strlen($batchJson) > self::MAX_BATCH_BYTES)
        {
            throw new HttpException(
                413,
                'Telemetry batch exceeds the maximum payload size.'
            );
        }

        foreach ($records as $record)
        {
            if (!is_array($record))
            {
                throw new HttpException(
                    422,
                    'Telemetry record must be an object.'
                );
            }

            try
            {
                $recordJson = $this->canonicalJson($record);
            }
            catch (Throwable $exception)
            {
                throw new HttpException(
                    422,
                    'Telemetry record contains invalid JSON data.'
                );
            }

            if (strlen($recordJson) > self::MAX_RECORD_BYTES)
            {
                throw new HttpException(
                    413,
                    'Telemetry record exceeds the maximum size.'
                );
            }
        }
    }

    private function validateApplicationScope(
        string $streamName,
        ?string $applicationId,
        NadiAgent $agent,
        array &$applicationCache
    ): void
    {
        $requiresApplication = str_starts_with(
            $streamName,
            'application_'
        );

        if ($requiresApplication && !$applicationId)
        {
            throw new HttpException(
                422,
                'Application ID is required for this stream.'
            );
        }

        if (
            $streamName === 'server_metrics'
            && $applicationId !== null
        )
        {
            throw new HttpException(
                422,
                'Server metrics cannot have an application ID.'
            );
        }

        if (!$applicationId)
        {
            return;
        }

        if (isset($applicationCache[$applicationId]))
        {
            return;
        }

        $application = NadiApplication::query()
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

        if (!$this->isApplicationEligible(
            $application,
            $agent
        ))
        {
            throw new HttpException(
                403,
                'Application monitoring is not authorized.'
            );
        }

        $applicationCache[$applicationId] = $application;
    }

    private function isApplicationEligible(
        NadiApplication $application,
        NadiAgent $agent
    ): bool
    {
        if ($agent->installation_state !== 'installed')
        {
            return false;
        }

        if (
            $application->registration_status !== 'approved'
            || !in_array(
                $application->activation_status,
                ['active'],
                true
            )
            || $application->monitoring_suspended_at !== null
        )
        {
            return false;
        }

        $grants = NadiActivationGrant::query()
            ->with('registration')
            ->where('agent_id', $agent->id)
            ->where('application_id', $application->id)
            ->where('grant_status', 'active')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->orderByDesc('grant_version')
            ->orderByDesc('issued_at')
            ->get();

        foreach ($grants as $grant)
        {
            if ($this->isGrantEligible(
                $grant,
                $application,
                $agent
            ))
            {
                return true;
            }
        }

        return false;
    }

    private function isGrantEligible(
        NadiActivationGrant $grant,
        NadiApplication $application,
        NadiAgent $agent
    ): bool
    {
        if (
            $grant->grant_status !== 'active'
            || $grant->acknowledgement_status !== 'success'
            || $grant->revoked_at !== null
            || !$grant->expires_at
            || !$grant->expires_at->isFuture()
        )
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
            || (int) ($payload['grant_version'] ?? 0)
                !== (int) $grant->grant_version
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

    private function resolveStream(
        NadiAgent $agent,
        ?string $applicationId,
        string $streamName
    ): string
    {
        $scope = $applicationId ?? 'server';

        $streamKey = hash(
            'sha256',
            $scope . '|' . $streamName
        );

        $source = $this->resolveSource($streamName);

        DB::table('nadi_telemetry_streams')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'application_id' => $applicationId,
            'stream_key' => $streamKey,
            'stream_name' => $streamName,
            'source' => $source,
            'created_at' => now('UTC')->format('Y-m-d H:i:s.u'),
            'updated_at' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);

        $stream = DB::table('nadi_telemetry_streams')
            ->where('agent_id', $agent->id)
            ->where('stream_key', $streamKey)
            ->lockForUpdate()
            ->first();

        if (!$stream)
        {
            throw new HttpException(
                500,
                'Unable to initialize telemetry stream.'
            );
        }

        return $stream->id;
    }

    private function resolveSource(string $streamName): string
    {
        if (str_starts_with($streamName, 'nginx_'))
        {
            return 'nginx';
        }

        if (str_starts_with($streamName, 'application_'))
        {
            return 'laravel';
        }

        return 'agent';
    }

    private function isHttpStream(string $streamName): bool
    {
        return in_array($streamName, [
            'nginx_http_requests',
            'application_http_requests',
            'application_http_errors',
        ], true);
    }

    private function storeTelemetryRecord(
        array $record,
        string $batchInternalId,
        string $streamId,
        NadiAgent $agent
    ): void
    {
        $data = [
            'metric_set' => $record['metric_set'] ?? null,
            'data' => $record['data'],
        ];

        DB::table('nadi_telemetry_records')->insert([
            'id' => (string) Str::uuid(),
            'batch_id' => $batchInternalId,
            'stream_id' => $streamId,
            'agent_id' => $agent->id,
            'application_id' => $record['application_id'] ?? null,
            'record_id' => $record['record_id'],
            'sequence' => $record['sequence'],
            'observed_at' => $this->toUtcDateTime($record['observed_at']),
            'collected_at' => $this->toUtcDateTime($record['collected_at']),
            'data' => json_encode(
                $data,
                JSON_THROW_ON_ERROR
            ),
            'created_at' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }
    
    private function storeHttpEvent(
        array $record,
        string $batchInternalId,
        string $streamId,
        NadiAgent $agent
    ): void
    {
        $data = $record['data'];

        $source = $this->resolveSource($record['stream']);

        $method = strtoupper(
            (string) ($data['http_method'] ?? '')
        );

        if (
            $method === ''
            || strlen($method) > 16
            || !preg_match('/^[A-Z!#$%&\'*+.^_`|~-]+$/', $method)
        )
        {
            throw new HttpException(
                422,
                'HTTP event requires a valid HTTP method.'
            );
        }

        $clientIp = $data['client_ip'] ?? null;

        if (
            $clientIp !== null
            && (
                !is_string($clientIp)
                || filter_var($clientIp, FILTER_VALIDATE_IP) === false
            )
        )
        {
            throw new HttpException(
                422,
                'Invalid client IP address.'
            );
        }

        $status = $data['http_status'] ?? null;

        if (
            $status !== null
            && (
                !is_numeric($status)
                || (int) $status < 100
                || (int) $status > 599
            )
        )
        {
            throw new HttpException(
                422,
                'Invalid HTTP response status.'
            );
        }

        $uri = $data['request_uri'] ?? null;

        if ($uri !== null)
        {
            if (!is_string($uri) || strlen($uri) > 8192)
            {
                throw new HttpException(
                    422,
                    'Invalid HTTP request URI.'
                );
            }

            $uri = parse_url($uri, PHP_URL_PATH);

            if (!is_string($uri) || $uri === '')
            {
                $uri = '/';
            }

            $uri = $this->redactTelemetryString(
                $uri,
                2048
            );
        }

        $eventStatus = $data['event_status'] ?? 'completed';

        if (!in_array($eventStatus, [
            'completed',
            'aborted',
            'timeout',
            'connection_error',
            'unknown',
        ], true))
        {
            throw new HttpException(
                422,
                'Invalid HTTP event status.'
            );
        }

        $requestId = $this->validatedTelemetryString(
            $data['request_id'] ?? null,
            100,
            'request_id'
        );

        $host = $this->validatedTelemetryString(
            $data['host'] ?? null,
            255,
            'host'
        );

        $route = $this->validatedTelemetryString(
            $data['route'] ?? null,
            512,
            'route'
        );

        $exceptionClass = $this->validatedTelemetryString(
            $data['exception_class'] ?? null,
            255,
            'exception_class'
        );

        $errorMessage = $this->validatedTelemetryString(
            $data['error_message'] ?? null,
            2000,
            'error_message'
        );

        $userAgent = $this->validatedTelemetryString(
            $data['user_agent'] ?? null,
            512,
            'user_agent'
        );

        $requestSize = $this->validatedTelemetryInteger(
            $data['request_size_bytes'] ?? null,
            'request_size_bytes'
        );

        $responseSize = $this->validatedTelemetryInteger(
            $data['response_size_bytes'] ?? null,
            'response_size_bytes'
        );

        $duration = $this->validatedTelemetryDecimal(
            $data['duration_ms'] ?? null,
            'duration_ms'
        );

        $upstreamDuration = $this->validatedTelemetryDecimal(
            $data['upstream_response_time_ms'] ?? null,
            'upstream_response_time_ms'
        );

        $metadata = $this->sanitizeMetadata(
            $data['metadata'] ?? []
        );

        DB::table('nadi_http_request_events')->insert([
            'id' => (string) Str::uuid(),
            'batch_id' => $batchInternalId,
            'stream_id' => $streamId,
            'agent_id' => $agent->id,
            'application_id' => $record['application_id'] ?? null,
            'record_id' => $record['record_id'],
            'sequence' => $record['sequence'],
            'request_id' => $requestId,
            'source' => $source,
            'event_status' => $eventStatus,
            'observed_at' => $this->toUtcDateTime(
                $record['observed_at']
            ),
            'client_ip' => $clientIp,
            'http_method' => $method,
            'host' => $host,
            'request_uri' => $uri,
            'route' => $route,
            'http_status' => $status,
            'request_size_bytes' => $requestSize,
            'response_size_bytes' => $responseSize,
            'duration_ms' => $duration,
            'upstream_response_time_ms' => $upstreamDuration,
            'exception_class' => $exceptionClass,
            'error_message' => $errorMessage,
            'user_agent' => $userAgent,
            'metadata' => json_encode(
                $metadata,
                JSON_THROW_ON_ERROR
            ),
            'created_at' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }


        private function sanitizeMetadata(array $metadata): array
    {
        if (count($metadata) > 100)
        {
            throw new HttpException(
                422,
                'Telemetry metadata contains too many fields.'
            );
        }

        $blockedKeys = [
            'authorization',
            'password',
            'passwd',
            'token',
            'access_token',
            'refresh_token',
            'id_token',
            'cookie',
            'set-cookie',
            'session',
            'secret',
            'api_key',
            'apikey',
            'private_key',
            'client_secret',
            'credential',
        ];

        $sanitized = [];

        foreach ($metadata as $key => $value)
        {
            $normalizedKey = strtolower(
                str_replace('-', '_', (string) $key)
            );

            if (in_array($normalizedKey, $blockedKeys, true))
            {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value))
            {
                $sanitized[$key] = $this->sanitizeMetadata(
                    $value
                );

                continue;
            }

            if (is_string($value))
            {
                $sanitized[$key] = $this->redactTelemetryString(
                    $value,
                    512
                );

                continue;
            }

            if (
                is_null($value)
                || is_bool($value)
                || is_int($value)
                || is_float($value)
            )
            {
                $sanitized[$key] = $value;

                continue;
            }

            $sanitized[$key] = '[UNSUPPORTED_VALUE]';
        }

        $encoded = json_encode(
            $sanitized,
            JSON_THROW_ON_ERROR
        );

        if (strlen($encoded) > 16384)
        {
            throw new HttpException(
                422,
                'Telemetry metadata exceeds the maximum size.'
            );
        }

        return $sanitized;
    }

    private function redactTelemetryString(
        string $value,
        int $maxLength
    ): string
    {
        $patterns = [
            '#Bearer\s+[A-Za-z0-9._\x7E+\/=-]+#i',
            '#(password|passwd|token|access_token|refresh_token|api_key|secret)\s*[:=]\s*[^\s&,;]+#i',
            '#(authorization|cookie|set-cookie)\s*[:=]\s*[^\r\n]*#i',
        ];

        $value = preg_replace(
            $patterns,
            '[REDACTED]',
            $value
        );

        if ($value === null)
        {
            throw new HttpException(
                422,
                'Unable to sanitize telemetry data.'
            );
        }

        return mb_substr(
            $value,
            0,
            $maxLength
        );
    }


    private function validatedTelemetryString(
        mixed $value,
        int $maxLength,
        string $field
    ): ?string
    {
        if ($value === null)
        {
            return null;
        }

        if (!is_string($value))
        {
            throw new HttpException(
                422,
                'Invalid telemetry field: ' . $field
            );
        }

        return $this->redactTelemetryString(
            $value,
            $maxLength
        );
    }

    private function validatedTelemetryInteger(
        mixed $value,
        string $field
    ): ?int
    {
        if ($value === null)
        {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
            || (int) $value < 0
        )
        {
            throw new HttpException(
                422,
                'Invalid telemetry field: ' . $field
            );
        }

        return (int) $value;
    }

    private function validatedTelemetryDecimal(
        mixed $value,
        string $field
    ): ?float
    {
        if ($value === null)
        {
            return null;
        }

        if (
            !is_numeric($value)
            || !is_finite((float) $value)
            || (float) $value < 0
            || (float) $value > 99999999999.999
        )
        {
            throw new HttpException(
                422,
                'Invalid telemetry field: ' . $field
            );
        }

        return round((float) $value, 3);
    }

    private function updateSequence(
        string $streamId,
        NadiAgent $agent,
        array $record
    ): void
    {
        $sequence = (int) $record['sequence'];

        $stream = DB::table('nadi_telemetry_streams')
            ->where('id', $streamId)
            ->lockForUpdate()
            ->first();

        $contiguous = (int) $stream->last_contiguous_sequence;
        $maximum = (int) $stream->max_sequence_seen;

        if ($sequence > $contiguous + 1)
        {
            $gapFrom = $contiguous + 1;
            $gapTo = $sequence - 1;

            $existingGap = DB::table('nadi_telemetry_gaps')
                ->where('stream_id', $streamId)
                ->where('gap_status', 'open')
                ->where('missing_sequence_from', $gapFrom)
                ->where('missing_sequence_to', $gapTo)
                ->exists();

            if (!$existingGap)
            {
                DB::table('nadi_telemetry_gaps')->insert([
                    'id' => (string) Str::uuid(),
                    'stream_id' => $streamId,
                    'agent_id' => $agent->id,
                    'missing_sequence_from' => $gapFrom,
                    'missing_sequence_to' => $gapTo,
                    'gap_started_at' => $this->toUtcDateTime(
                        $record['observed_at']
                    ),
                    'gap_detected_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                    'gap_status' => 'open',
                    'description' => 'Missing telemetry sequence detected.',
                    'created_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                    'updated_at' => now('UTC')
                        ->format('Y-m-d H:i:s.u'),
                ]);
            }
        }

        if ($sequence > $maximum)
        {
            $maximum = $sequence;
        }

        while (
            DB::table('nadi_telemetry_record_keys')
                ->where('stream_id', $streamId)
                ->where('sequence', $contiguous + 1)
                ->exists()
        )
        {
            $contiguous++;
        }

        DB::table('nadi_telemetry_streams')
            ->where('id', $streamId)
            ->update([
                'last_contiguous_sequence' => $contiguous,
                'max_sequence_seen' => $maximum,
                'last_observed_at' => $this->toUtcDateTime(
                    $record['observed_at']
                ),
                'last_received_at' => now('UTC')
                    ->format('Y-m-d H:i:s.u'),
            ]);

        $this->resolveFilledGaps($streamId);
    }

    private function resolveFilledGaps(string $streamId): void
    {
        $gaps = DB::table('nadi_telemetry_gaps')
            ->where('stream_id', $streamId)
            ->where('gap_status', 'open')
            ->get();

        foreach ($gaps as $gap)
        {
            if (
                $gap->missing_sequence_from === null
                || $gap->missing_sequence_to === null
            )
            {
                continue;
            }

            $expected = (
                (int) $gap->missing_sequence_to
                - (int) $gap->missing_sequence_from
                + 1
            );

            $received = DB::table('nadi_telemetry_record_keys')
                ->where('stream_id', $streamId)
                ->whereBetween('sequence', [
                    $gap->missing_sequence_from,
                    $gap->missing_sequence_to,
                ])
                ->count();

            if ($received >= $expected)
            {
                DB::table('nadi_telemetry_gaps')
                    ->where('id', $gap->id)
                    ->update([
                        'gap_status' => 'resolved',
                        'gap_resolved_at' => now('UTC')
                            ->format('Y-m-d H:i:s.u'),
                        'updated_at' => now('UTC')
                            ->format('Y-m-d H:i:s.u'),
                    ]);
            }
        }
    }

    private function toUtcDateTime(string $value): string
    {
        try
        {
            return \Illuminate\Support\Carbon::parse($value)
                ->utc()
                ->format('Y-m-d H:i:s.u');
        }
        catch (Throwable $exception)
        {
            throw new HttpException(
                422,
                'Invalid telemetry timestamp.'
            );
        }
    }

    private function canonicalJson(array $value): string
    {
        $normalized = $this->normalize($value);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    }

    private function normalize(array $value): array
    {
        foreach ($value as $key => $item)
        {
            if (is_array($item))
            {
                $value[$key] = $this->normalize($item);
            }
        }

        if (!array_is_list($value))
        {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private function validateBatchAge(mixed $sentAt): void
    {
        if (!is_string($sentAt) || trim($sentAt) === '')
        {
            throw new HttpException(
                422,
                'Telemetry batch sent_at is invalid.'
            );
        }

        $retentionDays = DB::table('nadi_retention_policies')
            ->where('data_type', 'telemetry_batches')
            ->value('retention_days');

        if (
            !is_numeric($retentionDays)
            || (int) $retentionDays < 1
        )
        {
            throw new HttpException(
                503,
                'Telemetry batch retention policy is unavailable or invalid.'
            );
        }

        try
        {
            $sentAtUtc = Carbon::parse($sentAt)->utc();
        }
        catch (Throwable $exception)
        {
            throw new HttpException(
                422,
                'Telemetry batch sent_at is invalid.'
            );
        }

        $cutoff = now('UTC')->subDays(
            (int) $retentionDays
        );

        if ($sentAtUtc->lt($cutoff))
        {
            throw new HttpException(
                410,
                'Telemetry batch has exceeded the configured retention period.'
            );
        }
    }

    private function replayExistingBatch(
        object $existingBatch,
        array $payload
    ): array
    {
        if (
            !hash_equals(
                (string) $existingBatch->payload_hash,
                hash('sha256', $this->canonicalJson($payload))
            )
        )
        {
            throw new HttpException(
                409,
                'Batch ID already exists with a different payload.'
            );
        }

        if ($existingBatch->batch_status !== 'accepted')
        {
            throw new HttpException(
                409,
                'Batch already exists but has not been accepted.'
            );
        }

        if (
            !is_string($existingBatch->ack_response)
            || trim($existingBatch->ack_response) === ''
        )
        {
            throw new HttpException(
                500,
                'Accepted telemetry batch has no acknowledgement response.'
            );
        }

        try
        {
            $ack = json_decode(
                $existingBatch->ack_response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }
        catch (\JsonException $exception)
        {
            report($exception);

            throw new HttpException(
                500,
                'Stored telemetry acknowledgement is invalid.'
            );
        }

        if (
            !is_array($ack)
            || ($ack['success'] ?? false) !== true
            || ($ack['status'] ?? null) !== 'accepted'
            || ($ack['batch_id'] ?? null) !== $payload['batch_id']
        )
        {
            throw new HttpException(
                500,
                'Stored telemetry acknowledgement is inconsistent.'
            );
        }

        $ack['replayed'] = true;

        return $ack;
    }
}
