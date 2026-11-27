<?php

namespace App\Services\Notification;

use Illuminate\Validation\ValidationException;

class NotificationTemplateRenderer
{
    private const SYSTEM_PLACEHOLDERS = [
        'recipient_email',
        'current_date',
        'current_datetime',
    ];

    private const INPUT_PLACEHOLDERS = [
        'application_name',
        'server_name',
        'severity',
        'event_name',
        'event_time',
        'message',
    ];

    public function supportedPlaceholders(): array
    {
        return [
            ...self::SYSTEM_PLACEHOLDERS,
            ...self::INPUT_PLACEHOLDERS,
        ];
    }

    public function render(
        string $subject,
        string $body,
        string $recipient,
        array $values
    ): array
    {
        $placeholders = [
            'recipient_email' => $recipient,
            'current_date' => now()->format('d-m-Y'),
            'current_datetime' => now()->format('d-m-Y H:i:s'),
            'application_name' => config('app.name', 'NADI'),
            'server_name' => '',
            'severity' => '',
            'event_name' => '',
            'event_time' => '',
            'message' => '',
        ];

        foreach (self::INPUT_PLACEHOLDERS as $key)
        {
            if (array_key_exists($key, $values))
            {
                $placeholders[$key] = trim((string) $values[$key]);
            }
        }

        $unknownPlaceholders = [];

        foreach ([$subject, $body] as $content)
        {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $content, $matches);

            foreach ($matches[1] as $placeholder)
            {
                if (!array_key_exists($placeholder, $placeholders))
                {
                    $unknownPlaceholders[] = $placeholder;
                }
            }
        }

        $unknownPlaceholders = array_values(array_unique($unknownPlaceholders));

        if ($unknownPlaceholders !== [])
        {
            throw ValidationException::withMessages([
                'body' => [
                    'Unsupported email placeholder(s): '
                    . implode(', ', array_map(
                        fn (string $placeholder) => '{{' . $placeholder . '}}',
                        $unknownPlaceholders
                    ))
                    . '. Check the supported placeholder list.',
                ],
            ]);
        }

        $render = function (string $content) use ($placeholders): string
        {
            return preg_replace_callback(
                '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
                function (array $matches) use ($placeholders): string
                {
                    return $placeholders[$matches[1]];
                },
                $content
            );
        };

        $renderedSubject = $render($subject);
        $renderedBody = $render($body);

        if (mb_strlen($renderedSubject) > 255)
        {
            throw ValidationException::withMessages([
                'subject' => 'Rendered email subject must not exceed 255 characters.',
            ]);
        }

        if (mb_strlen($renderedBody) > 20000)
        {
            throw ValidationException::withMessages([
                'body' => 'Rendered email body must not exceed 20,000 characters.',
            ]);
        }

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
        ];
    }
}
