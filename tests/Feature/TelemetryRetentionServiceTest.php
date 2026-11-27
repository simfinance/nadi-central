<?php

namespace Tests\Feature;

use App\Models\NadiAgent;
use App\Models\NadiServer;
use App\Services\Nadi\TelemetryRetentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelemetryRetentionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expired_records_are_deleted_before_expired_batches(): void
    {
        $fixture = $this->createTelemetryFixture(true);

        $this->createRetentionPolicy('telemetry_records', 30);
        $this->createRetentionPolicy('telemetry_batches', 30);

        $result = app(TelemetryRetentionService::class)->prune();

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['deleted']['telemetry_records']);
        $this->assertSame(1, $result['deleted']['telemetry_batches']);

        $this->assertDatabaseMissing('nadi_telemetry_records', [
            'id' => $fixture['record_internal_id'],
        ]);

        $this->assertDatabaseMissing('nadi_telemetry_batches', [
            'id' => $fixture['batch_internal_id'],
        ]);
    }

    public function test_expired_batch_is_preserved_while_dependent_record_remains(): void
    {
        $fixture = $this->createTelemetryFixture(false);

        $this->createRetentionPolicy('telemetry_batches', 30);

        $result = app(TelemetryRetentionService::class)->prune();

        $this->assertSame('completed', $result['status']);
        $this->assertSame(0, $result['deleted']['telemetry_batches']);

        $this->assertDatabaseHas('nadi_telemetry_records', [
            'id' => $fixture['record_internal_id'],
        ]);

        $this->assertDatabaseHas('nadi_telemetry_batches', [
            'id' => $fixture['batch_internal_id'],
        ]);
    }

    private function createTelemetryFixture(bool $expiredRecord): array
    {
        $server = NadiServer::create([
            'hostname' => 'nadi-retention-test-' . Str::uuid(),
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

        $batchInternalId = (string) Str::uuid();
        $batchId = (string) Str::uuid();
        $streamId = (string) Str::uuid();
        $recordId = (string) Str::uuid();
        $recordInternalId = (string) Str::uuid();

        $expiredAt = now('UTC')
            ->subDays(45)
            ->format('Y-m-d H:i:s.u');

        $currentTime = now('UTC')
            ->format('Y-m-d H:i:s.u');

        DB::table('nadi_telemetry_batches')->insert([
            'id' => $batchInternalId,
            'agent_id' => $agent->id,
            'batch_id' => $batchId,
            'protocol_version' => '1',
            'payload_hash' => hash('sha256', $batchId),
            'sent_at' => $expiredAt,
            'received_at' => $expiredAt,
            'batch_status' => 'accepted',
            'record_count' => 1,
            'accepted_count' => 1,
            'duplicate_count' => 0,
            'rejected_count' => 0,
            'ack_response' => json_encode([
                'success' => true,
                'status' => 'accepted',
                'batch_id' => $batchId,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $expiredAt,
        ]);

        DB::table('nadi_telemetry_streams')->insert([
            'id' => $streamId,
            'agent_id' => $agent->id,
            'application_id' => null,
            'stream_key' => hash('sha256', $streamId),
            'stream_name' => 'server_metrics',
            'source' => 'agent',
            'last_contiguous_sequence' => 1,
            'max_sequence_seen' => 1,
            'last_observed_at' => $currentTime,
            'last_received_at' => $currentTime,
            'created_at' => $currentTime,
            'updated_at' => $currentTime,
        ]);

        DB::table('nadi_telemetry_records')->insert([
            'id' => $recordInternalId,
            'batch_id' => $batchInternalId,
            'stream_id' => $streamId,
            'agent_id' => $agent->id,
            'application_id' => null,
            'record_id' => $recordId,
            'sequence' => 1,
            'observed_at' => $expiredRecord ? $expiredAt : $currentTime,
            'collected_at' => $expiredRecord ? $expiredAt : $currentTime,
            'data' => json_encode([
                'cpu_percent' => 25.5,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $currentTime,
        ]);

        return [
            'batch_internal_id' => $batchInternalId,
            'record_id' => $recordId,
            'record_internal_id' => $recordInternalId,
        ];
    }

    private function createRetentionPolicy(
        string $dataType,
        int $retentionDays
    ): void {
        $now = now('UTC')->format('Y-m-d H:i:s.u');

        DB::table('nadi_retention_policies')->insert([
            'id' => (string) Str::uuid(),
            'data_type' => $dataType,
            'retention_days' => $retentionDays,
            'enabled' => 1,
            'description' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
