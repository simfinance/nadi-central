<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class GmailNotificationService
{
    private const MAILER = 'nadi_gmail';

    private const SMTP_HOST = 'smtp.gmail.com';

    private const SMTP_PORT = 587;

    public function sendTestMessage(string $recipient): void
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
                'encryption' => 'tls',
            ],
        ]);

        Mail::purge(self::MAILER);

        try
        {
            Mail::mailer(self::MAILER)->raw(
                'This is a test email from NADI Central. Your Gmail SMTP configuration is working.',
                function ($message) use ($recipient, $settings)
                {
                    $message
                        ->to($recipient)
                        ->from($settings->smtp_username, $settings->from_name)
                        ->subject('[NADI] Gmail SMTP configuration test');
                }
            );

            DB::table('nadi_notification_mail_settings')
                ->where('id', $settings->id)
                ->update([
                    'last_tested_at' => now(),
                    'updated_at' => now(),
                ]);
        }
        catch (Throwable $exception)
        {
            report($exception);

            throw new RuntimeException(
                'Unable to send the test email. Check the Gmail address, App Password, and Google account SMTP access.',
                previous: $exception
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

        $data = [
            'smtp_username' => strtolower(trim($username)),
            'from_name' => trim($fromName),
            'enabled' => $enabled,
            'updated_at' => now(),
        ];

        if ($password !== null && $password !== '')
        {
            $data['smtp_password'] = Crypt::encryptString($password);
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
