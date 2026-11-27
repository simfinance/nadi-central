<?php

namespace App\Services\Activation;

use RuntimeException;

class GrantSigner
{
    public function sign(array $payload): string
    {
        $secretKey = $this->readSecretKey();
        $publicKey = $this->readPublicKey();
        $derivedPublicKey = sodium_crypto_sign_publickey_from_secretkey(
            $secretKey
        );

        if (!hash_equals($publicKey, $derivedPublicKey))
        {
            throw new RuntimeException(
                'Activation Grant Ed25519 public and private keys do not match.'
            );
        }

        $signature = sodium_crypto_sign_detached(
            $this->canonicalJson($payload),
            $secretKey
        );

        return base64_encode($signature);
    }

    public function verify(
        array $payload,
        string $base64Signature
    ): bool {
        $publicKey = $this->readPublicKey();

        $signature = base64_decode(
            $base64Signature,
            true
        );

        if (
            $signature === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
        )
        {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $signature,
            $this->canonicalJson($payload),
            $publicKey
        );
    }

    public function canonicalJson(array $payload): string
    {
        $normalized = $this->normalize($payload);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    }

    private function readSecretKey(): string
    {
        if (!function_exists('sodium_crypto_sign_seed_keypair'))
        {
            throw new RuntimeException(
                'The PHP sodium extension is required for Activation Grant Ed25519 signing.'
            );
        }

        $content = $this->readKeyFile(
            config('nadi.activation.private_key_path')
        );

        if (strlen($content) === SODIUM_CRYPTO_SIGN_SEEDBYTES)
        {
            $keyPair = sodium_crypto_sign_seed_keypair($content);

            return sodium_crypto_sign_secretkey($keyPair);
        }

        if (strlen($content) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)
        {
            return $content;
        }

        $decoded = base64_decode(trim($content), true);

        if (
            $decoded !== false
            && strlen($decoded) === SODIUM_CRYPTO_SIGN_SEEDBYTES
        )
        {
            $keyPair = sodium_crypto_sign_seed_keypair($decoded);

            return sodium_crypto_sign_secretkey($keyPair);
        }

        if (
            $decoded !== false
            && strlen($decoded) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
        )
        {
            return $decoded;
        }

        throw new RuntimeException(
            'Activation Grant private key must be a raw or base64 Ed25519 seed/secret key.'
        );
    }

    private function readPublicKey(): string
    {
        if (!function_exists('sodium_crypto_sign_verify_detached'))
        {
            throw new RuntimeException(
                'The PHP sodium extension is required for Activation Grant Ed25519 verification.'
            );
        }

        $content = $this->readKeyFile(
            config('nadi.activation.public_key_path')
        );

        if (strlen($content) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)
        {
            throw new RuntimeException(
                'Activation Grant public key must be a raw 32-byte Ed25519 public key.'
            );
        }

        return $content;
    }

    private function readKeyFile(string $path): string
    {
        if ($path === '' || !is_file($path))
        {
            throw new RuntimeException(
                'Activation Grant key file does not exist.'
            );
        }

        $content = file_get_contents($path);

        if ($content === false || $content === '')
        {
            throw new RuntimeException(
                'Unable to read Activation Grant key file.'
            );
        }

        return $content;
    }

    private function normalize(array $value): array
    {
        foreach ($value as $key => $item)
        {
            if (is_array($item))
            {
                $value[$key] = $this->normalize($item);
            }
        }

        if (!array_is_list($value))
        {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
