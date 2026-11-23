<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Nadi\AgentControlController;
use App\Models\NadiActivationGrant;
use App\Models\NadiAgent;
use App\Models\NadiApplication;
use App\Models\NadiApplicationEndpoint;
use App\Models\NadiRegistration;
use App\Models\NadiServer;
use App\Services\Activation\ActivationAcknowledgementService;
use App\Services\Activation\ActivationGrantService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivationGrantRetryTest extends TestCase
{
    use DatabaseTransactions;

    protected array $keyPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestKeys();
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

    public function test_pending_grant_is_reused_without_creating_new_version(): void
    {
        $identity = $this->createIdentity();

        $service = app(ActivationGrantService::class);

        $firstGrant = $service->issue(
            $identity['registration']->id
        );

        $secondGrant = $service->issue(
            $identity['registration']->id
        );

        $this->assertSame(
            $firstGrant->id,
            $secondGrant->id
        );

        $this->assertSame(
            1,
            NadiActivationGrant::where(
                'registration_id',
                $identity['registration']->id
            )->count()
        );

        $this->assertSame(
            'pending',
            $secondGrant->fresh()->acknowledgement_status
        );

        $this->assertSame(
            1,
            $secondGrant->fresh()->grant_version
        );
    }

    public function test_failed_grant_is_preserved_and_new_version_is_sent_by_control_poll(): void
    {
        $identity = $this->createIdentity();

        $grantService = app(ActivationGrantService::class);

        $acknowledgementService = app(
            ActivationAcknowledgementService::class
        );

        // Issue grant version 1.

        $firstGrant = $grantService->issue(
            $identity['registration']->id
        );

        $this->assertSame(
            1,
            $firstGrant->grant_version
        );

        // Simulate an actual Agent failure acknowledgement.

        $acknowledgementService->acknowledge(
            $identity['agent']->id,
            (string) Str::uuid(),
            $identity['application']->id,
            $firstGrant->id,
            'failed',
            'GRANT_VERIFICATION_FAILED',
            'Test failure acknowledgement.'
        );

        $firstGrant->refresh();

        $this->assertSame(
            'failed',
            $firstGrant->acknowledgement_status
        );

        $this->assertSame(
            'GRANT_VERIFICATION_FAILED',
            $firstGrant->acknowledgement_error_code
        );

        $this->assertSame(
            'failed',
            $identity['application']->fresh()->activation_status
        );

        // Issue a new grant after the failure.

        $secondGrant = $grantService->issue(
            $identity['registration']->id
        );

        $this->assertSame(
            2,
            $secondGrant->grant_version
        );

        $this->assertNotSame(
            $firstGrant->id,
            $secondGrant->id
        );

        // Verify the failed grant remains in history.

        $this->assertDatabaseHas('nadi_activation_grants', [
            'id' => $firstGrant->id,
            'grant_version' => 1,
            'acknowledgement_status' => 'failed',
            'acknowledgement_error_code' => 'GRANT_VERIFICATION_FAILED',
        ]);

        $this->assertDatabaseHas('nadi_activation_grants', [
            'id' => $secondGrant->id,
            'grant_version' => 2,
            'acknowledgement_status' => 'pending',
            'grant_status' => 'active',
        ]);

        $this->assertSame(
            2,
            NadiActivationGrant::where(
                'registration_id',
                $identity['registration']->id
            )->count()
        );

        // Verify Control Poll only sends grant version 2.

        $request = Request::create(
            '/api/v1/nadi/agent/control/poll',
            'POST'
        );

        $request->attributes->set(
            'nadi_agent',
            $identity['agent']
        );

        $controller = app(AgentControlController::class);

        $response = $controller->poll($request);

        $responseData = $response->getData(true);

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertTrue(
            $responseData['success']
        );

        $this->assertSame(
            $secondGrant->id,
            $responseData['data']['applications'][0]['activation_grant']['grant_id']
        );

        $this->assertSame(
            2,
            $responseData['data']['applications'][0]['activation_grant']['grant_version']
        );

        $this->assertTrue(
            $responseData['data']['applications'][0]['monitoring_enabled']
        );
    }

    public function test_success_ack_activates_application_and_is_idempotent(): void
    {
        $identity = $this->createIdentity();

        $grantService = app(ActivationGrantService::class);

        $acknowledgementService = app(
            ActivationAcknowledgementService::class
        );

        $grant = $grantService->issue(
            $identity['registration']->id
        );

        $requestId = (string) Str::uuid();

        $firstResult = $acknowledgementService->acknowledge(
            $identity['agent']->id,
            $requestId,
            $identity['application']->id,
            $grant->id,
            'success'
        );

        $this->assertFalse($firstResult['replayed']);

        $this->assertSame(
            'success',
            $firstResult['acknowledgement_status']
        );

        $this->assertSame(
            'active',
            $firstResult['application_status']
        );

        $application = $identity['application']->fresh();

        $this->assertSame(
            'active',
            $application->activation_status
        );

        $this->assertSame(
            'active',
            $application->monitoring_status
        );

        $this->assertSame(
            'success',
            $grant->fresh()->acknowledgement_status
        );

        $this->assertSame(
            $requestId,
            $grant->fresh()->acknowledgement_request_id
        );

        $this->assertSame(
            1,
            DB::table('nadi_approval_logs')
                ->where('action', 'activation_acknowledged')
                ->where('application_id', $identity['application']->id)
                ->count()
        );

        // Repeat the exact same acknowledgement request.

        $secondResult = $acknowledgementService->acknowledge(
            $identity['agent']->id,
            $requestId,
            $identity['application']->id,
            $grant->id,
            'success'
        );

        $this->assertTrue($secondResult['replayed']);

        $this->assertSame(
            'success',
            $secondResult['acknowledgement_status']
        );

        // Replay must not create another approval log.

        $this->assertSame(
            1,
            DB::table('nadi_approval_logs')
                ->where('action', 'activation_acknowledged')
                ->where('application_id', $identity['application']->id)
                ->count()
        );
    }

    public function test_success_ack_does_not_activate_suspended_monitoring(): void
    {
        $identity = $this->createIdentity();

        $grantService = app(ActivationGrantService::class);

        $acknowledgementService = app(
            ActivationAcknowledgementService::class
        );

        $grant = $grantService->issue(
            $identity['registration']->id
        );

        // Simulate a suspended monitoring state.

        DB::table('nadi_applications')
            ->where('id', $identity['application']->id)
            ->update([
                'monitoring_suspended_at' => now('UTC'),
                'monitoring_status' => 'stopped',
            ]);

        $requestId = (string) Str::uuid();

        $result = $acknowledgementService->acknowledge(
            $identity['agent']->id,
            $requestId,
            $identity['application']->id,
            $grant->id,
            'success'
        );

        $this->assertFalse($result['replayed']);

        $application = $identity['application']->fresh();

        $this->assertSame(
            'active',
            $application->activation_status
        );

        $this->assertSame(
            'stopped',
            $application->monitoring_status
        );

        $this->assertNotNull(
            $application->monitoring_suspended_at
        );

        $this->assertSame(
            'success',
            $grant->fresh()->acknowledgement_status
        );
    }

    public function test_acknowledgement_request_id_cannot_be_reused_for_another_grant(): void
    {
        $firstIdentity = $this->createIdentity();
        $secondIdentity = $this->createIdentity();

        $grantService = app(ActivationGrantService::class);

        $acknowledgementService = app(
            ActivationAcknowledgementService::class
        );

        $firstGrant = $grantService->issue(
            $firstIdentity['registration']->id
        );

        $secondGrant = $grantService->issue(
            $secondIdentity['registration']->id
        );

        $requestId = (string) Str::uuid();

        $acknowledgementService->acknowledge(
            $firstIdentity['agent']->id,
            $requestId,
            $firstIdentity['application']->id,
            $firstGrant->id,
            'success'
        );

        try
        {
            $acknowledgementService->acknowledge(
                $secondIdentity['agent']->id,
                $requestId,
                $secondIdentity['application']->id,
                $secondGrant->id,
                'success'
            );

            $this->fail(
                'Reusing an acknowledgement request ID must be rejected.'
            );
        }
        catch (\RuntimeException $exception)
        {
            $this->assertSame(
                'Acknowledgement request ID has already been used.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            'pending',
            $secondGrant->fresh()->acknowledgement_status
        );
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

        NadiApplicationEndpoint::create([
            'application_id' => $application->id,
            'base_url' => 'https://test.example.invalid',
            'endpoint_prefix' => '/_nadi/v1',
            'verification_status' => 'pending',
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
            'nadi.activation.capabilities' => [],
        ]);
    }

}
