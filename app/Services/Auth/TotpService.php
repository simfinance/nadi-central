<?php

namespace App\Services\Auth;

class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $email, string $secret): string
    {
        $issuer = 'NADI Central';
        $label = rawurlencode($issuer . ':' . $email);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    public function verify(string $secret, string $code, ?int $lastAcceptedStep = null): ?int
    {
        if (! preg_match('/^\d{6}$/', $code))
        {
            return null;
        }

        $currentStep = intdiv(time(), self::PERIOD);

        for ($step = $currentStep - 1; $step <= $currentStep + 1; $step++)
        {
            if ($step < 0 || ($lastAcceptedStep !== null && $step <= $lastAcceptedStep))
            {
                continue;
            }

            if (hash_equals($this->codeForStep($secret, $step), $code))
            {
                return $step;
            }
        }

        return null;
    }

    private function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N2', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($data) as $character)
        {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk)
        {
            if (strlen($chunk) === 5)
            {
                $encoded .= $alphabet[bindec($chunk)];
            }
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';

        foreach (str_split($secret) as $character)
        {
            $position = strpos($alphabet, $character);

            if ($position === false)
            {
                throw new \InvalidArgumentException('Invalid TOTP secret.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk)
        {
            if (strlen($chunk) === 8)
            {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
