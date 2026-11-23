<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class GmailNotificationService
{
    private const MAILER = 'nadi_gmail';

    private const SMTP_HOST = 'smtp.gmail.com';

    private const SMTP_PORT = 587;

    public function send(string $recipient, string $subject, string $body): void
    {
        $settings = $this->settings();

        if (!$settings || !$settings->enabled)
        {
            throw new RuntimeException('Gmail notification is not enabled.');
        }

        if (!$settings->smtp_username || !$settings->smtp_password)
        {
            throw new RuntimeException('Gmail credentials are incomplete.');
        }

        $password = Crypt::decryptString($settings->smtp_password);

        config([
            'mail.mailers.' . self::MAILER => [
                'transport' => 'smtp',
                'scheme' => 'smtp',
                'host' => self::SMTP_HOST,
                'port' => self::SMTP_PORT,
                'username' => $settings->smtp_username,
                'password' => $password,
                'timeout' => 15,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        ]);

        Mail::purge(self::MAILER);

        try
        {
            Mail::mailer(self::MAILER)->raw(
                $body,
                function ($message) use ($recipient, $subject, $settings)
                {
                    $message
                        ->to($recipient)
                        ->from($settings->smtp_username, $settings->from_name)
                        ->subject($subject);
                }
            );
        }
        catch (Throwable $exception)
        {
            Log::warning('NADI Gmail notification delivery failed.', [
                'exception_type' => get_class($exception),
            ]);

            throw new RuntimeException(
                'Unable to send email. Check the Gmail address, App Password, and Google account SMTP access.'
            );
        }
        finally
        {
            Mail::purge(self::MAILER);

            config([
                'mail.mailers.' . self::MAILER => null,
            ]);
        }
    }

    public function sendTestMessage(string $recipient): void
    {
        $this->send(
            $recipient,
            '[NADI] Gmail SMTP configuration test',
            'This is a test email from NADI Central. Your Gmail SMTP configuration is working.'
        );

        DB::table('nadi_notification_mail_settings')
            ->where('id', 1)
            ->update([
                'last_tested_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function settings(): ?object
    {
        return DB::table('nadi_notification_mail_settings')
            ->where('id', 1)
            ->first();
    }

    public function saveSettings(
        string $username,
        ?string $password,
        string $fromName,
        bool $enabled
    ): void
    {
        $existing = $this->settings();
        $normalizedPassword = $password !== null
            ? preg_replace('/\s+/', '', trim($password))
            : null;

        $data = [
            'smtp_username' => strtolower(trim($username)),
            'from_name' => trim($fromName),
            'enabled' => $enabled,
            'updated_at' => now(),
        ];

        if ($normalizedPassword !== null && $normalizedPassword !== '')
        {
            $data['smtp_password'] = Crypt::encryptString($normalizedPassword);
        }

        if ($existing)
        {
            DB::table('nadi_notification_mail_settings')
                ->where('id', 1)
                ->update($data);

            return;
        }

        DB::table('nadi_notification_mail_settings')->insert([
            'id' => 1,
            ...$data,
            'created_at' => now(),
        ]);
    }
}
