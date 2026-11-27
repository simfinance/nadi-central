<?php

namespace Tests\Unit\Services\Activation;

use App\Services\Activation\GrantSigner;
use RuntimeException;
use Tests\TestCase;

class GrantSignerTest extends TestCase
{
    protected array $keyPaths = [];

    protected string $seed = '';

    protected string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed = random_bytes(
            SODIUM_CRYPTO_SIGN_SEEDBYTES
        );

        $keyPair = sodium_crypto_sign_seed_keypair($this->seed);
        $this->publicKey = sodium_crypto_sign_publickey($keyPair);

        $privatePath = tempnam(
            sys_get_temp_dir(),
            'nadi-grant-private-'
        );

        $publicPath = tempnam(
            sys_get_temp_dir(),
            'nadi-grant-public-'
        );

        if ($privatePath === false || $publicPath === false)
        {
            $this->fail(
                'Unable to create temporary Activation Grant key files.'
            );
        }

        file_put_contents($privatePath, $this->seed);
        file_put_contents($publicPath, $this->publicKey);

        $this->keyPaths = [
            $privatePath,
            $publicPath,
        ];

        config([
            'nadi.activation.private_key_path' => $privatePath,
            'nadi.activation.public_key_path' => $publicPath,
        ]);

        sodium_memzero($keyPair);
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

        if ($this->seed !== '')
        {
            sodium_memzero($this->seed);
        }

        parent::tearDown();
    }

    public function test_activation_grant_signature_uses_detached_ed25519(): void
    {
        $payload = [
            'z' => 1,
            'capabilities' => [
                'server_metrics',
                'application_metrics',
            ],
            'a' => [
                'd' => 2,
                'b' => 1,
            ],
        ];

        $signer = app(GrantSigner::class);
        $signature = $signer->sign($payload);
        $decodedSignature = base64_decode($signature, true);

        $this->assertNotFalse($decodedSignature);
        $this->assertSame(
            SODIUM_CRYPTO_SIGN_BYTES,
            strlen($decodedSignature)
        );

        $this->assertTrue(
            sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $signer->canonicalJson($payload),
                $this->publicKey
            )
        );

        $this->assertTrue(
            $signer->verify($payload, $signature)
        );

        $payload['z'] = 2;

        $this->assertFalse(
            $signer->verify($payload, $signature)
        );
    }

    public function test_activation_grant_canonical_json_matches_agent_key_ordering(): void
    {
        $canonical = app(GrantSigner::class)->canonicalJson([
            'z' => 1,
            'capabilities' => [
                'server_metrics',
                'application_metrics',
            ],
            'a' => [
                'd' => 2,
                'b' => 1,
            ],
        ]);

        $this->assertSame(
            '{"a":{"b":1,"d":2},"capabilities":["server_metrics","application_metrics"],"z":1}',
            $canonical
        );
    }

    public function test_signing_rejects_a_mismatched_public_key(): void
    {
        $otherSeed = random_bytes(
            SODIUM_CRYPTO_SIGN_SEEDBYTES
        );

        $otherKeyPair = sodium_crypto_sign_seed_keypair($otherSeed);
        $otherPublicKey = sodium_crypto_sign_publickey($otherKeyPair);

        file_put_contents(
            $this->keyPaths[1],
            $otherPublicKey
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Activation Grant Ed25519 public and private keys do not match.'
        );

        try
        {
            app(GrantSigner::class)->sign([
                'grant_id' => 'test',
            ]);
        }
        finally
        {
            sodium_memzero($otherSeed);
            sodium_memzero($otherKeyPair);
        }
    }
    public function test_signature_matches_agent_ed25519_compatibility_vector(): void
    {
        $fixture = json_decode(
            file_get_contents(
                base_path('tests/fixtures/activation-grant-ed25519-vector.json')
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $seed = base64_decode(
            $fixture['private_seed_base64'],
            true
        );

        $this->assertNotFalse($seed);

        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

        file_put_contents($this->keyPaths[0], $seed);
        file_put_contents($this->keyPaths[1], $publicKey);

        $signer = app(GrantSigner::class);

        $this->assertSame(
            $fixture['canonical_json'],
            $signer->canonicalJson($fixture['payload'])
        );

        $this->assertSame(
            $fixture['signature_base64'],
            $signer->sign($fixture['payload'])
        );

        $this->assertTrue(
            $signer->verify(
                $fixture['payload'],
                $fixture['signature_base64']
            )
        );

        sodium_memzero($seed);
        sodium_memzero($keyPair);
    }


}
