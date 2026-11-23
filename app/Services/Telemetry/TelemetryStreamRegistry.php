<?php

namespace App\Services\Telemetry;

class TelemetryStreamRegistry
{
    private const STREAMS = [
        'server_metrics' => ['capability' => 'server_metrics', 'scope' => 'server', 'log' => false],
        'nginx_metrics' => ['capability' => 'server_metrics', 'scope' => 'server', 'log' => false],
        'nginx_vhost_metrics' => ['capability' => 'server_metrics', 'scope' => 'server', 'log' => false],
        'nginx_error_logs' => ['capability' => 'system_logs', 'scope' => 'server', 'log' => true],
        'application_metrics' => ['capability' => 'application_metrics', 'scope' => 'application', 'log' => false],
        'application_logs' => ['capability' => 'application_logs', 'scope' => 'application', 'log' => true],
        'database_metrics' => ['capability' => 'database_monitoring', 'scope' => 'application', 'log' => false],
        'database_connections' => ['capability' => 'database_monitoring', 'scope' => 'application', 'log' => false],
        'queue_metrics' => ['capability' => 'queue_monitoring', 'scope' => 'application', 'log' => false],
        'queue_jobs' => ['capability' => 'queue_monitoring', 'scope' => 'application', 'log' => false],
        'scheduler_metrics' => ['capability' => 'scheduler_monitoring', 'scope' => 'application', 'log' => false],
        'scheduler_events' => ['capability' => 'scheduler_monitoring', 'scope' => 'application', 'log' => false],
        'nginx_access_logs' => ['capability' => null, 'scope' => 'server', 'log' => true],
        'application_events' => ['capability' => null, 'scope' => 'application', 'log' => false],
        'nginx_http_requests' => ['capability' => null, 'scope' => 'server', 'log' => false],
        'application_http_requests' => ['capability' => null, 'scope' => 'application', 'log' => false],
        'application_http_errors' => ['capability' => null, 'scope' => 'application', 'log' => false],
    ];

    public static function all(): array
    {
        return self::STREAMS;
    }

    public static function recognizedNames(): array
    {
        return array_keys(self::STREAMS);
    }

    public static function definition(string $stream): ?array
    {
        return self::STREAMS[$stream] ?? null;
    }

    public static function isRecognized(string $stream): bool
    {
        return isset(self::STREAMS[$stream]);
    }

    public static function scope(string $stream): ?string
    {
        return self::definition($stream)['scope'] ?? null;
    }

    public static function isLog(string $stream): bool
    {
        return (self::definition($stream)['log'] ?? false) === true;
    }
}
