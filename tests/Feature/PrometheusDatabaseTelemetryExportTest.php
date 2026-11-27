<?php

namespace Tests\Feature;

use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Models\NadiServer;
use App\Models\NadiRegistration;
use App\Services\Activation\ActivationAcknowledgementService;
use App\Services\Activation\ActivationGrantService;
use App\Services\Telemetry\TelemetryIngestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrometheusDatabaseTelemetryExportTest extends TestCase
{
    use DatabaseTransactions;

    protected array $keyPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestKeys();

        config([
            'nadi.activation.capabilities' => [
                'server_metrics',
                'application_metrics',
                'database_monitoring',
                'queue_monitoring',
                'scheduler_monitoring',
                'system_logs',
                'application_logs',
            ],
            'nadi.prometheus.metrics_token' => 'test-prometheus-token',
        ]);

        $now = now('UTC')->format('Y-m-d H:i:s.u');

        DB::table('nadi_retention_policies')->insert([
            'id' => (string) Str::uuid(),
            'data_type' => 'telemetry_batches',
            'retention_days' => 30,
            'enabled' => 1,
            'description' => 'Prometheus database telemetry test policy.',
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->keyPaths as $path)
        {
            if (is_file($path))
            {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_database_probe_metrics_are_exported_and_stop_after_application_suspend(): void
    {
        $identity = $this->createIdentity();

        $grant = app(ActivationGrantService::class)->issue(
            $identity['registration']->id
        );

        app(ActivationAcknowledgementService::class)->acknowledge(
            $identity['agent']->id,
            (string) Str::uuid(),
            $identity['application']->id,
            $grant->id,
            'success'
        );

        $service = app(TelemetryIngestionService::class);

        $service->ingest(
            $this->makeBatch(
                $identity,
                1,
                [
                    'status' => 'ok',
                    'driver' => 'mysql',
                    'latency_ms' => 12.5,
                    'checked_at' => now('UTC')->toIso8601String(),
                    'cache_hit' => false,
                    'error_code' => null,
                ]
            ),
            $identity['agent']
        );

        $firstState = DB::table('nadi_telemetry_database_probe_states')
            ->where('application_id', $identity['application']->id)
            ->first();

        $this->assertNotNull($firstState);
        $this->assertSame('ok', $firstState->last_status);
        $this->assertSame(0, (int) $firstState->failures_total);
        $this->assertNotNull($firstState->last_success_at);

        $service->ingest(
            $this->makeBatch(
                $identity,
                2,
                [
                    'status' => 'failed',
                    'driver' => null,
                    'latency_ms' => 125.5,
                    'checked_at' => now('UTC')->toIso8601String(),
                    'cache_hit' => false,
                    'error_code' => 'database_connection_failed',
                ]
            ),
            $identity['agent']
        );

        $state = DB::table('nadi_telemetry_database_probe_states')
            ->where('application_id', $identity['application']->id)
            ->first();

        $this->assertSame('failed', $state->last_status);
        $this->assertSame(1, (int) $state->failures_total);
        $this->assertSame(125.5, (float) $state->last_latency_ms);
        $this->assertSame(
            $firstState->last_success_at,
            $state->last_success_at
        );

        $response = $this->withToken('test-prometheus-token')
            ->get('/api/internal/prometheus/metrics');

        $response->assertOk();

        $body = $response->getContent();

        $this->assertStringContainsString(
            'laravel_db_connection_status{agent_id="',
            $body
        );

        $this->assertStringContainsString(
            'laravel_db_connection_status{agent_id="'
            . $identity['agent']->id
            . '"',
            $body
        );

        $this->assertStringContainsString(
            'laravel_db_connection_duration_seconds{agent_id="'
            . $identity['agent']->id
            . '"',
            $body
        );

        $this->assertStringContainsString(
            'laravel_db_probe_failures_total{agent_id="'
            . $identity['agent']->id
            . '"',
            $body
        );

        $this->assertStringContainsString(
            'laravel_db_query_success{agent_id="'
            . $identity['agent']->id
            . '"',
            $body
        );

        $identity['application']->monitoring_suspended_at = now('UTC');
        $identity['application']->monitoring_status = 'stopped';
        $identity['application']->save();

        $suspendedResponse = $this->withToken('test-prometheus-token')
            ->get('/api/internal/prometheus/metrics');

        $suspendedResponse->assertOk();

        $this->assertStringNotContainsString(
            'laravel_db_connection_status{agent_id="'
            . $identity['agent']->id
            . '"',
            $suspendedResponse->getContent()
        );
    }


    public function test_out_of_order_database_probe_does_not_lose_last_success_timestamp(): void
    {
        $identity = $this->createIdentity();

        $grant = app(ActivationGrantService::class)->issue(
            $identity['registration']->id
        );

        app(ActivationAcknowledgementService::class)->acknowledge(
            $identity['agent']->id,
            (string) Str::uuid(),
            $identity['application']->id,
            $grant->id,
            'success'
        );

        $service = app(TelemetryIngestionService::class);
        $successAt = now('UTC')->subSeconds(5)->toIso8601String();

        $service->ingest(
            $this->makeBatch(
                $identity,
                2,
                [
                    'status' => 'failed',
                    'driver' => null,
                    'latency_ms' => 125.5,
                    'checked_at' => now('UTC')->toIso8601String(),
                    'cache_hit' => false,
                    'error_code' => 'database_connection_failed',
                ]
            ),
            $identity['agent']
        );

        $service->ingest(
            $this->makeBatch(
                $identity,
                1,
                [
                    'status' => 'ok',
                    'driver' => 'mysql',
                    'latency_ms' => 12.5,
                    'checked_at' => $successAt,
                    'cache_hit' => false,
                    'error_code' => null,
                ]
            ),
            $identity['agent']
        );

        $state = DB::table('nadi_telemetry_database_probe_states')
            ->where('application_id', $identity['application']->id)
            ->first();

        $this->assertSame(2, (int) $state->last_sequence);
        $this->assertSame('failed', $state->last_status);
        $this->assertSame(1, (int) $state->failures_total);
        $this->assertNotNull($state->last_success_at);
        $this->assertSame(
            Carbon::parse($successAt)->utc()->format('Y-m-d H:i:s'),
            substr($state->last_success_at, 0, 19)
        );
    }

    private function makeBatch(
        array $identity,
        int $sequence,
        array $data
    ): array {
        $timestamp = now('UTC')->toIso8601String();

        return [
            'protocol_version' => '1.0',
            'batch_id' => (string) Str::uuid(),
            'agent_instance_id' => $identity['agent']->agent_instance_id,
            'sent_at' => $timestamp,
            'records' => [
                [
                    'record_id' => (string) Str::uuid(),
                    'stream' => 'database_metrics',
                    'sequence' => $sequence,
                    'observed_at' => $timestamp,
                    'collected_at' => $timestamp,
                    'application_id' => $identity['application']->id,
                    'metric_set' => 'database_probe',
                    'data' => $data,
                ],
            ],
        ];
    }

    private function createIdentity(): array
    {
        $server = NadiServer::create([
            'hostname' => 'nadi-prometheus-test-' . Str::uuid(),
            'ip_address' => '127.0.0.1',
            'os_name' => 'Ubuntu',
            'os_version' => '26.04',
            'architecture' => 'x86_64',
            'status' => 'registered',
        ]);

        $agent = NadiAgent::create([
            'server_id' => $server->id,
            'agent_instance_id' => (string) Str::uuid(),
            'agent_version' => '1.0.0',
            'protocol_version' => '1',
            'installation_state' => 'installed',
            'status' => 'registered',
        ]);

        $application = NadiApplication::create([
            'agent_id' => $agent->id,
            'application_name' => 'NADI Prometheus Test Application',
            'application_key' => (string) Str::uuid(),
            'environment' => 'testing',
            'framework' => 'laravel',
            'framework_version' => '13',
            'php_version' => PHP_VERSION,
            'registration_status' => 'approved',
            'activation_status' => 'pending',
            'monitoring_status' => 'dormant',
        ]);

        $registration = NadiRegistration::create([
            'application_id' => $application->id,
            'agent_id' => $agent->id,
            'registration_status' => 'approved',
            'requested_at' => now('UTC'),
            'reviewed_at' => now('UTC'),
        ]);

        return [
            'server' => $server,
            'agent' => $agent,
            'application' => $application,
            'registration' => $registration,
        ];
    }

    private function configureTestKeys(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($key === false)
        {
            $this->fail(
                'Unable to generate temporary test signing key.'
            );
        }

        $privateKey = '';

        if (!openssl_pkey_export($key, $privateKey))
        {
            $this->fail(
                'Unable to export temporary test private key.'
            );
        }

        $keyDetails = openssl_pkey_get_details($key);

        if (!$keyDetails || empty($keyDetails['key']))
        {
            $this->fail(
                'Unable to export temporary test public key.'
            );
        }

        $privatePath = tempnam(
            sys_get_temp_dir(),
            'nadi-test-private-'
        );

        $publicPath = tempnam(
            sys_get_temp_dir(),
            'nadi-test-public-'
        );

        if ($privatePath === false || $publicPath === false)
        {
            $this->fail(
                'Unable to create temporary test key files.'
            );
        }

        file_put_contents($privatePath, $privateKey);

        file_put_contents(
            $publicPath,
            $keyDetails['key']
        );

        $this->keyPaths = [
            $privatePath,
            $publicPath,
        ];

        config([
            'nadi.activation.key_id' => 'test-key',
            'nadi.activation.private_key_path' => $privatePath,
            'nadi.activation.public_key_path' => $publicPath,
            'nadi.activation.grant_lifetime_days' => 30,
        ]);
    }
}
