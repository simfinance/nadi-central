<?php

namespace Tests\Feature;

use App\Models\NadiActivationGrant;
use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Models\NadiRegistration;
use App\Models\NadiServer;
use App\Services\Activation\ActivationAcknowledgementService;
use App\Services\Activation\ActivationGrantService;
use App\Services\Activation\GrantSigner;
use App\Services\Telemetry\TelemetryAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TelemetryAuthorizationTest extends TestCase
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

    public function test_application_telemetry_is_rejected_without_a_grant(): void
    {
        $identity = $this->createIdentity();

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $identity['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_pending_acknowledgement_does_not_authorize_telemetry(): void
    {
        $identity = $this->createIdentity();

        app(ActivationGrantService::class)->issue(
            $identity['registration']->id
        );

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $identity['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_expired_grant_does_not_authorize_telemetry(): void
    {
        $identity = $this->createIdentity();

        $grant = $this->issueAcknowledgedGrant($identity);

        $grant->expires_at = now('UTC')->subMinute();
        $grant->save();

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $identity['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_revoked_grant_does_not_authorize_telemetry(): void
    {
        $identity = $this->createIdentity();

        $grant = $this->issueAcknowledgedGrant($identity);

        $grant->grant_status = 'revoked';
        $grant->revoked_at = now('UTC');
        $grant->save();

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $identity['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_grant_without_required_capability_does_not_authorize_stream(): void
    {
        $identity = $this->createIdentity();

        config([
            'nadi.activation.capabilities' => [
                'application_metrics',
            ],
        ]);

        $this->issueAcknowledgedGrant($identity);

        $this->assertAuthorizationRejected(
            $this->payload('application_logs', $identity['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_application_telemetry_cannot_cross_agent_boundary(): void
    {
        $identity = $this->createIdentity();
        $foreignIdentity = $this->createIdentity();

        $this->issueAcknowledgedGrant($identity);

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $foreignIdentity['application']->id),
            $identity['agent'],
            403
        );
    }

    public function test_one_application_grant_does_not_authorize_another_application(): void
    {
        $identity = $this->createIdentity();
        $secondApplication = $this->createApplicationForAgent(
            $identity['agent']
        );

        $this->issueAcknowledgedGrant($identity);

        $this->assertAuthorizationRejected(
            $this->payload('application_metrics', $secondApplication['application']->id),
            $identity['agent'],
            410
        );
    }

    public function test_server_telemetry_requires_explicit_server_capability_in_an_active_application_grant(): void
    {
        $identity = $this->createIdentity();

        config([
            'nadi.activation.capabilities' => [
                'application_metrics',
            ],
        ]);

        $this->issueAcknowledgedGrant($identity);

        $this->assertAuthorizationRejected(
            $this->payload('server_metrics'),
            $identity['agent'],
            410
        );

        config([
            'nadi.activation.capabilities' => [
                'application_metrics',
                'server_metrics',
            ],
        ]);

        $secondIdentity = $this->createApplicationForAgent(
            $identity['agent']
        );

        $this->issueAcknowledgedGrant($secondIdentity);

        $authorized = app(TelemetryAuthorizationService::class)
            ->authorizeAndSanitize(
                $this->payload('server_metrics'),
                $identity['agent']
            );

        $this->assertSame(
            'server_metrics',
            $authorized['records'][0]['stream']
        );
    }

    public function test_suspending_one_application_does_not_block_another_eligible_application_grant(): void
    {
        $identity = $this->createIdentity();
        $secondApplication = $this->createApplicationForAgent(
            $identity['agent']
        );

        $this->issueAcknowledgedGrant($identity);
        $this->issueAcknowledgedGrant($secondApplication);

        $identity['application']->monitoring_suspended_at = now('UTC');
        $identity['application']->monitoring_status = 'stopped';
        $identity['application']->save();

        $authorized = app(TelemetryAuthorizationService::class)
            ->authorizeAndSanitize(
                $this->payload('application_metrics', $secondApplication['application']->id),
                $identity['agent']
            );

        $this->assertSame(
            $secondApplication['application']->id,
            $authorized['records'][0]['application_id']
        );

        $serverTelemetry = app(TelemetryAuthorizationService::class)
            ->authorizeAndSanitize(
                $this->payload('server_metrics'),
                $identity['agent']
            );

        $this->assertSame(
            'server_metrics',
            $serverTelemetry['records'][0]['stream']
        );
    }

    private function issueAcknowledgedGrant(array $identity): NadiActivationGrant
    {
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

        return $grant->fresh();
    }

    private function assertAuthorizationRejected(
        array $payload,
        NadiAgent $agent,
        int $expectedStatus
    ): void
    {
        try
        {
            app(TelemetryAuthorizationService::class)
                ->authorizeAndSanitize($payload, $agent);

            $this->fail(
                'Telemetry authorization should have been rejected.'
            );
        }
        catch (HttpException $exception)
        {
            $this->assertSame(
                $expectedStatus,
                $exception->getStatusCode()
            );
        }
    }

    private function payload(
        string $stream,
        ?string $applicationId = null
    ): array
    {
        $record = [
            'record_id' => (string) Str::uuid(),
            'stream' => $stream,
            'sequence' => 1,
            'observed_at' => now('UTC')->toISOString(),
            'collected_at' => now('UTC')->toISOString(),
            'metric_set' => 'test',
            'data' => [
                'value' => 1,
            ],
        ];

        if ($applicationId !== null)
        {
            $record['application_id'] = $applicationId;
        }

        return [
            'records' => [
                $record,
            ],
        ];
    }

    private function createIdentity(): array
    {
        $server = NadiServer::create([
            'hostname' => 'nadi-test-' . Str::uuid(),
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

        return [
            'server' => $server,
            'agent' => $agent,
            ...$this->createApplicationForAgent($agent),
        ];
    }

    private function createApplicationForAgent(NadiAgent $agent): array
    {
        $application = NadiApplication::create([
            'agent_id' => $agent->id,
            'application_name' => 'NADI Test Application',
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
            'agent' => $agent,
            'application' => $application,
            'registration' => $registration,
        ];
    }

    private function configureTestKeys(): void
    {
        $seed = random_bytes(
            SODIUM_CRYPTO_SIGN_SEEDBYTES
        );

        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

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

        file_put_contents($privatePath, $seed);
        file_put_contents($publicPath, $publicKey);

        $this->keyPaths = [
            $privatePath,
            $publicPath,
        ];

        sodium_memzero($seed);
        sodium_memzero($keyPair);

        config([
            'nadi.activation.key_id' => 'test-key',
            'nadi.activation.private_key_path' => $privatePath,
            'nadi.activation.public_key_path' => $publicPath,
            'nadi.activation.grant_lifetime_days' => 30,
        ]);
    }

}
