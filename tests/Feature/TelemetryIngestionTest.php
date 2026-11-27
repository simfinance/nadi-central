<?php

namespace Tests\Feature;

use App\Models\NadiAgent;
use App\Models\NadiServer;
use App\Services\Telemetry\TelemetryIngestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TelemetryIngestionTest extends TestCase
{
    use DatabaseTransactions;

    protected NadiAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = $this->createAgent();

        $now = now('UTC')->format('Y-m-d H:i:s.u');

        DB::table('nadi_retention_policies')->insert([
            'id' => (string) Str::uuid(),
            'data_type' => 'telemetry_batches',
            'retention_days' => 30,
            'enabled' => 1,
            'description' => 'Telemetry ingestion test policy.',
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_server_metrics_batch_is_accepted_and_stored(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 25.5,
                    'memory_used_bytes' => 1024000,
                ]
            ),
        ]);

        $result = app(TelemetryIngestionService::class)->ingest(
            $payload,
            $this->agent
        );

        $this->assertTrue($result['success']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame(1, $result['record_count']);
        $this->assertSame(1, $result['accepted_count']);
        $this->assertSame(0, $result['duplicate_count']);
        $this->assertFalse($result['replayed']);

        $this->assertDatabaseHas('nadi_telemetry_batches', [
            'agent_id' => $this->agent->id,
            'batch_id' => $payload['batch_id'],
            'batch_status' => 'accepted',
            'accepted_count' => 1,
        ]);

        $this->assertDatabaseHas('nadi_telemetry_records', [
            'agent_id' => $this->agent->id,
            'record_id' => $payload['records'][0]['record_id'],
            'sequence' => 1,
        ]);

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_identical_batch_replay_does_not_duplicate_records(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 30,
                ]
            ),
        ]);

        $service = app(TelemetryIngestionService::class);

        $firstResult = $service->ingest(
            $payload,
            $this->agent
        );

        $secondResult = $service->ingest(
            $payload,
            $this->agent
        );

        $this->assertFalse($firstResult['replayed']);
        $this->assertTrue($secondResult['replayed']);

        $this->assertSame(
            $firstResult['batch_id'],
            $secondResult['batch_id']
        );

        $this->assertSame(
            1,
            $secondResult['accepted_count']
        );

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('nadi_telemetry_record_keys')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_same_batch_id_with_different_payload_returns_conflict(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ]
            ),
        ]);

        $service = app(TelemetryIngestionService::class);

        $service->ingest(
            $payload,
            $this->agent
        );

        $modifiedPayload = $payload;

        $modifiedPayload['records'][0]['data']['cpu_percent'] = 90;

        try
        {
            $service->ingest(
                $modifiedPayload,
                $this->agent
            );

            $this->fail(
                'Different payload with the same batch ID must be rejected.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_same_record_id_with_different_payload_returns_conflict(): void
    {
        $recordId = (string) Str::uuid();

        $firstPayload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ],
                $recordId
            ),
        ]);

        $secondPayload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 80,
                ],
                $recordId
            ),
        ]);

        $service = app(TelemetryIngestionService::class);

        $service->ingest(
            $firstPayload,
            $this->agent
        );

        try
        {
            $service->ingest(
                $secondPayload,
                $this->agent
            );

            $this->fail(
                'Reusing a record ID with different content must be rejected.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_record_keys')
                ->where('agent_id', $this->agent->id)
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_sequence_gap_is_recorded_when_sequence_is_missing(): void
    {
        $firstPayload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ]
            ),
        ]);

        $thirdPayload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                3,
                [
                    'cpu_percent' => 30,
                ]
            ),
        ]);

        $service = app(TelemetryIngestionService::class);

        $service->ingest(
            $firstPayload,
            $this->agent
        );

        $service->ingest(
            $thirdPayload,
            $this->agent
        );

        $this->assertDatabaseHas('nadi_telemetry_gaps', [
            'agent_id' => $this->agent->id,
            'missing_sequence_from' => 2,
            'missing_sequence_to' => 2,
            'gap_status' => 'open',
        ]);

        $this->assertSame(
            2,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_missing_sequence_is_resolved_when_record_arrives(): void
    {
        $service = app(TelemetryIngestionService::class);

        $service->ingest(
            $this->makeBatch([
                $this->makeRecord(
                    'server_metrics',
                    1,
                    [
                        'cpu_percent' => 20,
                    ]
                ),
            ]),
            $this->agent
        );

        $service->ingest(
            $this->makeBatch([
                $this->makeRecord(
                    'server_metrics',
                    3,
                    [
                        'cpu_percent' => 30,
                    ]
                ),
            ]),
            $this->agent
        );

        $service->ingest(
            $this->makeBatch([
                $this->makeRecord(
                    'server_metrics',
                    2,
                    [
                        'cpu_percent' => 25,
                    ]
                ),
            ]),
            $this->agent
        );

        $this->assertDatabaseHas('nadi_telemetry_gaps', [
            'agent_id' => $this->agent->id,
            'missing_sequence_from' => 2,
            'missing_sequence_to' => 2,
            'gap_status' => 'resolved',
        ]);

        $this->assertSame(
            3,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_agent_instance_identity_mismatch_is_rejected(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ]
            ),
        ]);

        $payload['agent_instance_id'] = (string) Str::uuid();

        try
        {
            app(TelemetryIngestionService::class)->ingest(
                $payload,
                $this->agent
            );

            $this->fail(
                'Agent instance identity mismatch must be rejected.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(
            0,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
        $this->assertSame(
            0,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_uninstalled_agent_cannot_submit_telemetry(): void
    {
        DB::table('nadi_agents')
            ->where('id', $this->agent->id)
            ->update([
                'installation_state' => 'uninstalled',
            ]);

        $this->agent->refresh();

        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ]
            ),
        ]);

        try
        {
            app(TelemetryIngestionService::class)->ingest(
                $payload,
                $this->agent
            );

            $this->fail(
                'Uninstalled Agent must not submit telemetry.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(
            0,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_application_stream_requires_application_id(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'application_metrics',
                1,
                [
                    'request_count' => 10,
                ]
            ),
        ]);

        try
        {
            app(TelemetryIngestionService::class)->ingest(
                $payload,
                $this->agent
            );

            $this->fail(
                'Application stream without application ID must be rejected.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(
            0,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_server_metrics_reject_application_id(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'server_metrics',
                1,
                [
                    'cpu_percent' => 20,
                ],
                null,
                (string) Str::uuid()
            ),
        ]);

        try
        {
            app(TelemetryIngestionService::class)->ingest(
                $payload,
                $this->agent
            );

            $this->fail(
                'Server metrics must not have an application ID.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(
            0,
            DB::table('nadi_telemetry_batches')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    public function test_http_request_event_is_stored_separately(): void
    {
        $payload = $this->makeBatch([
            $this->makeRecord(
                'nginx_http_requests',
                1,
                [
                    'request_id' => (string) Str::uuid(),
                    'http_method' => 'GET',
                    'host' => 'example.test',
                    'request_uri' => '/health?token=secret',
                    'http_status' => 200,
                    'client_ip' => '127.0.0.1',
                    'request_size_bytes' => 120,
                    'response_size_bytes' => 500,
                    'duration_ms' => 12.5,
                    'event_status' => 'completed',
                ]
            ),
        ]);

        $result = app(TelemetryIngestionService::class)->ingest(
            $payload,
            $this->agent
        );

        $this->assertTrue($result['success']);

        $this->assertSame(
            1,
            DB::table('nadi_http_request_events')
                ->where('agent_id', $this->agent->id)
                ->count()
        );

        $this->assertDatabaseHas('nadi_http_request_events', [
            'agent_id' => $this->agent->id,
            'http_method' => 'GET',
            'host' => 'example.test',
            'request_uri' => '/health',
            'http_status' => 200,
        ]);

        $this->assertSame(
            0,
            DB::table('nadi_telemetry_records')
                ->where('agent_id', $this->agent->id)
                ->count()
        );
    }

    private function createAgent(): NadiAgent
    {
        $server = NadiServer::create([
            'hostname' => 'nadi-telemetry-test',
            'ip_address' => '127.0.0.1',
            'os_name' => 'Ubuntu',
            'os_version' => '26.04',
            'architecture' => 'x86_64',
            'status' => 'registered',
        ]);

        return NadiAgent::create([
            'server_id' => $server->id,
            'agent_instance_id' => (string) Str::uuid(),
            'agent_version' => '1.0.0',
            'protocol_version' => '1',
            'installation_state' => 'installed',
            'status' => 'registered',
        ]);
    }

    private function makeBatch(array $records): array
    {
        return [
            'protocol_version' => '1',
            'batch_id' => (string) Str::uuid(),
            'agent_instance_id' => $this->agent->agent_instance_id,
            'sent_at' => now('UTC')->toIso8601String(),
            'records' => $records,
        ];
    }

    private function makeRecord(
        string $stream,
        int $sequence,
        array $data,
        ?string $recordId = null,
        ?string $applicationId = null
    ): array
    {
        return [
            'record_id' => $recordId ?? (string) Str::uuid(),
            'stream' => $stream,
            'sequence' => $sequence,
            'observed_at' => now('UTC')->toIso8601String(),
            'collected_at' => now('UTC')->toIso8601String(),
            'application_id' => $applicationId,
            'metric_set' => null,
            'data' => $data,
        ];
    }

}
