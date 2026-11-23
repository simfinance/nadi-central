<?php

namespace App\Http\Controllers\Api\V1\Nadi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReleaseManifestController extends Controller
{
    private const PRIVATE_KEY_PATH = '/etc/nadi-central/release-signing/release-signing-private.pem';

    private const KEY_ID = 'nadi-agent-release-2026-01';

    public function show(): Response
    {
        try
        {
            $manifest = [
                'schema_version' => 1,
                'product' => 'nadi-agent',
                'key_id' => self::KEY_ID,
                'version' => '0.7.5-dev',
                'protocol_version' => 1,
                'released_at' => '2026-11-22T06:34:20Z',
                'artifacts' => [
                    [
                        'os' => 'linux',
                        'architecture' => 'amd64',
                        'filename' => 'nadi-agent-linux-amd64',
                        'url' => 'https://github.com/simfinance/nadi-agent/releases/download/v0.7.5-dev/nadi-agent-linux-amd64',
                        'sha256' => '1eeb2946e3c7109cbfb2b41aa2c5ef7219e9bc006461e4674d1c6eaea604162c',
                    ],
                    [
                        'os' => 'linux',
                        'architecture' => 'arm64',
                        'filename' => 'nadi-agent-linux-arm64',
                        'url' => 'https://github.com/simfinance/nadi-agent/releases/download/v0.7.5-dev/nadi-agent-linux-arm64',
                        'sha256' => 'e13abf92acab374e44254bdb77230e0639df31efc81dc5ff12ae1e13227bd125',
                    ],
                ],
            ];

            $manifestJson = json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );

            if (!is_readable(self::PRIVATE_KEY_PATH))
            {
                return $this->signingUnavailable();
            }

            $privateKeyPem = file_get_contents(self::PRIVATE_KEY_PATH);

            if ($privateKeyPem === false)
            {
                return $this->signingUnavailable();
            }

            $privateKey = openssl_pkey_get_private($privateKeyPem);

            if ($privateKey === false)
            {
                return $this->signingUnavailable();
            }

            $keyDetails = openssl_pkey_get_details($privateKey);

            if (
                $keyDetails === false
                || !defined('OPENSSL_KEYTYPE_ED25519')
                || $keyDetails['type'] !== OPENSSL_KEYTYPE_ED25519
            )
            {
                return $this->signingUnavailable();
            }

            $signature = '';

            $signed = openssl_sign(
                $manifestJson,
                $signature,
                $privateKey,
                0
            );

            if (!$signed || $signature === '')
            {
                return $this->signingUnavailable();
            }

            return response($manifestJson, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-NADI-Manifest-Signature' => base64_encode($signature),
                'Cache-Control' => 'public, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        catch (Throwable $exception)
        {
            Log::error('NADI release manifest signing failed.', [
                'exception' => $exception::class,
            ]);

            return $this->signingUnavailable();
        }
    }

    private function signingUnavailable(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Release manifest is temporarily unavailable.',
        ], 503, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
