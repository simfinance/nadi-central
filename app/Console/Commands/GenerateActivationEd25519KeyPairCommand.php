<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class GenerateActivationEd25519KeyPairCommand extends Command
{
    protected $signature = 'nadi:activation-keys:generate';

    protected $description = 'Generate a new Ed25519 Activation Grant key pair without overwriting existing keys.';

    public function handle(): int
    {
        if (!function_exists('sodium_crypto_sign_seed_keypair'))
        {
            $this->error(
                'The PHP sodium extension is required.'
            );

            return self::FAILURE;
        }

        $privatePath = (string) config(
            'nadi.activation.private_key_path'
        );

        $publicPath = (string) config(
            'nadi.activation.public_key_path'
        );

        if (
            $privatePath === ''
            || $publicPath === ''
            || $privatePath === $publicPath
        )
        {
            $this->error(
                'Activation Grant private/public key paths are invalid.'
            );

            return self::FAILURE;
        }

        if (
            file_exists($privatePath)
            || is_link($privatePath)
            || file_exists($publicPath)
            || is_link($publicPath)
        )
        {
            $this->error(
                'An Activation Grant key file already exists. Existing keys are never overwritten.'
            );

            return self::FAILURE;
        }

        try
        {
            $this->ensureDirectory(dirname($privatePath));
            $this->ensureDirectory(dirname($publicPath));

            $seed = random_bytes(
                SODIUM_CRYPTO_SIGN_SEEDBYTES
            );

            $keyPair = sodium_crypto_sign_seed_keypair($seed);
            $secretKey = sodium_crypto_sign_secretkey($keyPair);
            $publicKey = sodium_crypto_sign_publickey($keyPair);

            $privateWritten = file_put_contents(
                $privatePath,
                $seed,
                LOCK_EX
            );

            if ($privateWritten !== SODIUM_CRYPTO_SIGN_SEEDBYTES)
            {
                throw new RuntimeException(
                    'Unable to write the Activation Grant private seed.'
                );
            }

            if (!chmod($privatePath, 0600))
            {
                @unlink($privatePath);

                throw new RuntimeException(
                    'Unable to restrict Activation Grant private seed permissions.'
                );
            }

            $publicWritten = file_put_contents(
                $publicPath,
                $publicKey,
                LOCK_EX
            );

            if ($publicWritten !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)
            {
                @unlink($privatePath);
                @unlink($publicPath);

                throw new RuntimeException(
                    'Unable to write the Activation Grant public key.'
                );
            }

            if (!chmod($publicPath, 0644))
            {
                @unlink($privatePath);
                @unlink($publicPath);

                throw new RuntimeException(
                    'Unable to set Activation Grant public key permissions.'
                );
            }

            sodium_memzero($seed);
            sodium_memzero($secretKey);
            sodium_memzero($keyPair);

            $this->info(
                'Ed25519 Activation Grant key pair generated.'
            );

            $this->line(
                'Private seed: ' . $privatePath
            );

            $this->line(
                'Public key: ' . $publicPath
            );

            $this->line(
                'Key ID: ' . config('nadi.activation.key_id')
            );

            $this->warn(
                'Distribute the raw 32-byte public key to Agents through the approved secure process before issuing grants.'
            );

            return self::SUCCESS;
        }
        catch (Throwable $exception)
        {
            $this->error(
                'Activation Grant key generation failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory))
        {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory))
        {
            throw new RuntimeException(
                'Unable to create Activation Grant key directory: '
                . $directory
            );
        }
    }
}
