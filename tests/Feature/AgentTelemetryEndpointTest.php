<?php

namespace Tests\Feature;

use App\Models\NadiAgent;
use App\Models\NadiServer;
use App\Services\Telemetry\TelemetryIngestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AgentTelemetryEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected NadiAgent $agent;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAgent();

        $this->token = 'nadi_agent_' . str_repeat('a', 48);

        $this->createCredential(
            $this->token
        );
    }

    public function test_telemetry_endpoint_rejects_missing_bearer_token(): void
    {
        $response = $this->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $this->makePayload()
        );

        $response->assertUnauthorized();

        $response->assertJson([
            'success' => false,
            'message' => 'Agent authentication required.',
        ]);
    }

    public function test_telemetry_endpoint_rejects_invalid_bearer_token(): void
    {
        $response = $this->withToken(
            str_repeat('x', 64)
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $this->makePayload()
        );

        $response->assertUnauthorized();

        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or revoked agent credential.',
        ]);
    }

    public function test_telemetry_endpoint_rejects_revoked_credential(): void
    {
        DB::table('nadi_agent_credentials')
            ->where('credential_hash', hash('sha256', $this->token))
            ->update([
                'status' => 'revoked',
                'revoked_at' => now()->toDateTimeString(),
            ]);

        $response = $this->withToken(
            $this->token
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $this->makePayload()
        );

        $response->assertUnauthorized();
    }

    public function test_telemetry_endpoint_rejects_expired_credential(): void
    {
        DB::table('nadi_agent_credentials')
            ->where('credential_hash', hash('sha256', $this->token))
            ->update([
                'expires_at' => now()
                    ->subMinute()
                    ->toDateTimeString(),
            ]);

        $response = $this->withToken(
            $this->token
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $this->makePayload()
        );

        $response->assertUnauthorized();
    }

    public function test_telemetry_endpoint_rejects_uninstalled_agent(): void
    {
        DB::table('nadi_agents')
            ->where('id', $this->agent->id)
            ->update([
                'installation_state' => 'uninstalled',
            ]);

        $response = $this->withToken(
            $this->token
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $this->makePayload()
        );

        $response->assertForbidden();

        $response->assertJson([
            'success' => false,
            'message' => 'Agent installation is inactive.',
        ]);
    }

    public function test_telemetry_endpoint_accepts_authenticated_agent_request(): void
    {
        $payload = $this->makePayload();

        $acknowledgement = [
            'success' => true,
            'batch_id' => $payload['batch_id'],
            'status' => 'accepted',
            'record_count' => 1,
            'accepted_count' => 1,
            'duplicate_count' => 0,
            'rejected_count' => 0,
            'replayed' => false,
        ];

        $authorization = Mockery::mock(
            TelemetryAuthorizationService::class
        );

        $authorization->shouldReceive('authorizeAndSanitize')
            ->once()
            ->withArgs(function (
                array $receivedPayload,
                NadiAgent $receivedAgent
            ) use ($payload)
            {
                return $receivedPayload === $payload
                    && $receivedAgent->id === $this->agent->id;
            })
            ->andReturn($payload);

        $this->app->instance(
            TelemetryAuthorizationService::class,
            $authorization
        );

        $service = Mockery::mock(
            TelemetryIngestionService::class
        );

        $service->shouldReceive('ingest')
            ->once()
            ->withArgs(function (
                array $receivedPayload,
                NadiAgent $receivedAgent
            ) use ($payload)
            {
                return $receivedPayload === $payload
                    && $receivedAgent->id === $this->agent->id;
            })
            ->andReturn($acknowledgement);

        $this->app->instance(
            TelemetryIngestionService::class,
            $service
        );

        $response = $this->withToken(
            $this->token
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            $payload
        );

        $response->assertOk();

        $response->assertJson([
            'success' => true,
            'data' => $acknowledgement,
        ]);

        $this->assertNotNull(
            DB::table('nadi_agent_credentials')
                ->where(
                    'credential_hash',
                    hash('sha256', $this->token)
                )
                ->value('last_used_at')
        );
    }

    public function test_telemetry_endpoint_rejects_invalid_payload(): void
    {
        $response = $this->withToken(
            $this->token
        )->postJson(
            '/api/v1/nadi/agent/telemetry/batches',
            [
                'protocol_version' => '1.0',
                'batch_id' => 'invalid-uuid',
                'agent_instance_id' => $this->agent->agent_instance_id,
                'sent_at' => now()->toIso8601String(),
                'records' => [],
            ]
        );

        $response->assertUnprocessable();

        $response->assertJson([
            'success' => false,
            'message' => 'Telemetry payload validation failed.',
        ]);
    }

    protected function createAgent(): void
    {
        $server = NadiServer::create([
            'hostname' => 'nadi-endpoint-test-' . Str::uuid(),
            'ip_address' => '127.0.0.1',
            'os_name' => 'Ubuntu',
            'os_version' => '26.04',
            'architecture' => 'x86_64',
            'status' => 'registered',
        ]);

        $this->agent = NadiAgent::create([
            'server_id' => $server->id,
            'agent_instance_id' => (string) Str::uuid(),
            'agent_version' => '1.0.0',
            'protocol_version' => '1',
            'installation_state' => 'installed',
            'status' => 'registered',
        ]);
    }

    protected function createCredential(string $token): void
    {
        DB::table('nadi_agent_credentials')->insert([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'credential_hash' => hash('sha256', $token),
            'key_version' => '1',
            'status' => 'active',
            'issued_at' => now()->toDateTimeString(),
            'expires_at' => null,
            'revoked_at' => null,
            'last_used_at' => null,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    protected function makePayload(): array
    {
        return [
            'protocol_version' => '1.0',
            'batch_id' => (string) Str::uuid(),
            'agent_instance_id' => $this->agent->agent_instance_id,
            'sent_at' => now()->toIso8601String(),
            'records' => [
                [
                    'record_id' => (string) Str::uuid(),
                    'stream' => 'server_metrics',
                    'sequence' => 1,
                    'observed_at' => now()->toIso8601String(),
                    'collected_at' => now()->toIso8601String(),
                    'application_id' => null,
                    'metric_set' => 'system',
                    'data' => [
                        'cpu_percent' => 25.5,
                        'memory_used_bytes' => 1024000,
                    ],
                ],
            ],
        ];
    }
}
