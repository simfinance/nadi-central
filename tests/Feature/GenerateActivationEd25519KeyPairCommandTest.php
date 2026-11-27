<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateActivationEd25519KeyPairCommandTest extends TestCase
{
    protected string $directory = '';

    protected string $privatePath = '';

    protected string $publicPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            . '/nadi-ed25519-'
            . Str::uuid();

        $this->assertTrue(
            mkdir($this->directory, 0700, true)
        );

        $this->privatePath = $this->directory
            . '/activation-seed.key';

        $this->publicPath = $this->directory
            . '/activation-public.key';

        config([
            'nadi.activation.private_key_path' => $this->privatePath,
            'nadi.activation.public_key_path' => $this->publicPath,
            'nadi.activation.key_id' => 'test-ed25519-key',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->privatePath))
        {
            unlink($this->privatePath);
        }

        if (is_file($this->publicPath))
        {
            unlink($this->publicPath);
        }

        if (is_dir($this->directory))
        {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_command_generates_raw_ed25519_keys_with_restrictive_permissions(): void
    {
        $exitCode = Artisan::call(
            'nadi:activation-keys:generate'
        );

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->privatePath);
        $this->assertFileExists($this->publicPath);
        $this->assertSame(
            SODIUM_CRYPTO_SIGN_SEEDBYTES,
            filesize($this->privatePath)
        );
        $this->assertSame(
            SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES,
            filesize($this->publicPath)
        );
        $this->assertSame(
            0600,
            fileperms($this->privatePath) & 0777
        );
        $this->assertSame(
            0644,
            fileperms($this->publicPath) & 0777
        );

        $seed = file_get_contents($this->privatePath);
        $publicKey = file_get_contents($this->publicPath);
        $keyPair = sodium_crypto_sign_seed_keypair($seed);

        $this->assertSame(
            sodium_crypto_sign_publickey($keyPair),
            $publicKey
        );

        sodium_memzero($seed);
        sodium_memzero($keyPair);
    }

    public function test_command_does_not_overwrite_existing_keys(): void
    {
        $firstExitCode = Artisan::call(
            'nadi:activation-keys:generate'
        );

        $this->assertSame(0, $firstExitCode);

        $privateKey = file_get_contents($this->privatePath);
        $publicKey = file_get_contents($this->publicPath);

        $secondExitCode = Artisan::call(
            'nadi:activation-keys:generate'
        );

        $this->assertSame(1, $secondExitCode);
        $this->assertSame(
            $privateKey,
            file_get_contents($this->privatePath)
        );
        $this->assertSame(
            $publicKey,
            file_get_contents($this->publicPath)
        );
    }
}
