<?php

namespace Tests\Unit\Services\Telemetry;

use App\Services\Activation\GrantSigner;
use App\Services\Telemetry\TelemetryAuthorizationService;
use App\Services\Telemetry\TelemetryStreamRegistry;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ReflectionMethod;

class TelemetryAuthorizationServiceTest extends TestCase
{
    private TelemetryAuthorizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TelemetryAuthorizationService(
            app(GrantSigner::class)
        );
    }

    public function test_stream_capability_mapping_is_explicit(): void
    {
        $mapping = [];

        foreach (TelemetryStreamRegistry::all() as $stream => $definition)
        {
            if ($definition['capability'] !== null)
            {
                $mapping[$stream] = $definition['capability'];
            }
        }

        $this->assertSame('server_metrics', $mapping['server_metrics']);
        $this->assertSame('application_metrics', $mapping['application_metrics']);
        $this->assertSame('database_monitoring', $mapping['database_metrics']);
        $this->assertSame('queue_monitoring', $mapping['queue_metrics']);
        $this->assertSame('scheduler_monitoring', $mapping['scheduler_metrics']);
        $this->assertSame('system_logs', $mapping['nginx_error_logs']);
        $this->assertSame('application_logs', $mapping['application_logs']);
        $this->assertArrayNotHasKey('nginx_access_logs', $mapping);
    }

    public function test_activation_capability_allowlist_contains_log_capabilities(): void
    {
        $capabilities = config('nadi.activation.capabilities');

        $this->assertContains('server_metrics', $capabilities);
        $this->assertContains('application_metrics', $capabilities);
        $this->assertContains('database_monitoring', $capabilities);
        $this->assertContains('queue_monitoring', $capabilities);
        $this->assertContains('scheduler_monitoring', $capabilities);
        $this->assertContains('system_logs', $capabilities);
        $this->assertContains('application_logs', $capabilities);
    }

    public function test_nginx_error_log_schema_redacts_secrets(): void
    {
        $sanitized = $this->invokePrivate(
            'validateAndSanitizeLog',
            [
                'nginx_error_logs',
                [
                    'timestamp' => '2026-11-23T10:00:00Z',
                    'severity' => 'ERROR',
                    'message' => 'Authorization: Bearer secret-token password=hunter2',
                ],
            ]
        );

        $this->assertSame('error', $sanitized['severity']);
        $this->assertSame('nginx', $sanitized['source']);
        $this->assertStringNotContainsString(
            'secret-token',
            $sanitized['message']
        );
        $this->assertStringNotContainsString(
            'hunter2',
            $sanitized['message']
        );
        $this->assertStringContainsString(
            '[REDACTED]',
            $sanitized['message']
        );
    }

    public function test_application_log_schema_redacts_sensitive_context_keys(): void
    {
        $sanitized = $this->invokePrivate(
            'validateAndSanitizeLog',
            [
                'application_logs',
                [
                    'timestamp' => '2026-11-23T10:00:00Z',
                    'level' => 'warning',
                    'channel' => 'production',
                    'message' => 'request failed api_key=secret-value',
                    'context' => [
                        'Authorization' => 'Bearer secret-token',
                        'request_id' => 'req-123',
                    ],
                ],
            ]
        );

        $this->assertSame(
            '[REDACTED]',
            $sanitized['context']['Authorization']
        );
        $this->assertSame(
            'req-123',
            $sanitized['context']['request_id']
        );
        $this->assertStringNotContainsString(
            'secret-value',
            $sanitized['message']
        );
    }

    public function test_log_schema_rejects_unknown_fields(): void
    {
        $this->expectException(HttpException::class);

        $this->invokePrivate(
            'validateAndSanitizeLog',
            [
                'application_logs',
                [
                    'timestamp' => '2026-11-23T10:00:00Z',
                    'level' => 'error',
                    'channel' => 'production',
                    'message' => 'failed',
                    'stack_trace' => 'raw trace',
                ],
            ]
        );
    }

    public function test_log_schema_rejects_raw_stack_traces(): void
    {
        $this->expectException(HttpException::class);

        $this->invokePrivate(
            'validateAndSanitizeLog',
            [
                'application_logs',
                [
                    'timestamp' => '2026-11-23T10:00:00Z',
                    'level' => 'error',
                    'channel' => 'production',
                    'message' => "Stack trace:\n#0 /srv/app.php",
                ],
            ]
        );
    }

    private function invokePrivate(
        string $method,
        array $arguments
    ): mixed
    {
        $reflection = new ReflectionMethod(
            TelemetryAuthorizationService::class,
            $method
        );

        return $reflection->invokeArgs(
            $this->service,
            $arguments
        );
    }
}
