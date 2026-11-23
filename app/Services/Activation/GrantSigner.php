<?php

namespace App\Services\Activation;

use RuntimeException;

class GrantSigner
{
    public function sign(array $payload): string
    {
        $privateKeyPath = config(
            'nadi.activation.private_key_path'
        );

        $privateKeyContent = $this->readKey($privateKeyPath);

        $privateKey = openssl_pkey_get_private(
            $privateKeyContent
        );

        if (!$privateKey)
        {
            throw new RuntimeException(
                'Unable to load NADI activation private key.'
            );
        }

        $message = $this->canonicalJson($payload);

        $signature = '';

        $result = openssl_sign(
            $message,
            $signature,
            $privateKey,
            0
        );

        if (!$result)
        {
            throw new RuntimeException(
                'NADI activation grant signing failed.'
            );
        }

        return base64_encode($signature);
    }

    public function verify(
        array $payload,
        string $base64Signature
    ): bool
    {
        $publicKeyPath = config(
            'nadi.activation.public_key_path'
        );

        $publicKeyContent = $this->readKey($publicKeyPath);

        $publicKey = openssl_pkey_get_public(
            $publicKeyContent
        );

        if (!$publicKey)
        {
            throw new RuntimeException(
                'Unable to load NADI activation public key.'
            );
        }

        $signature = base64_decode(
            $base64Signature,
            true
        );

        if ($signature === false)
        {
            return false;
        }

        $message = $this->canonicalJson($payload);

        $result = openssl_verify(
            $message,
            $signature,
            $publicKey,
            0
        );

        return $result === 1;
    }

    public function canonicalJson(array $payload): string
    {
        $normalized = $this->normalize($payload);

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );

        return $json;
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

        $isList = array_is_list($value);

        if (!$isList)
        {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private function readKey(string $path): string
    {
        if (!is_file($path))
        {
            throw new RuntimeException(
                'NADI activation key file does not exist.'
            );
        }

        $content = file_get_contents($path);

        if ($content === false)
        {
            throw new RuntimeException(
                'Unable to read NADI activation key file.'
            );
        }

        return $content;
    }
}
