<?php

return [
    'activation' => [
        'key_id' => env(
            'NADI_ACTIVATION_KEY_ID',
            'nadi-central-2026-01'
        ),

        'private_key_path' => env(
            'NADI_ACTIVATION_PRIVATE_KEY',
            '/etc/nadi-central/keys/activation-private.pem'
        ),

        'public_key_path' => env(
            'NADI_ACTIVATION_PUBLIC_KEY',
            '/etc/nadi-central/keys/activation-public.pem'
        ),

        'grant_lifetime_days' => (int) env(
            'NADI_ACTIVATION_GRANT_LIFETIME',
            30
        ),

        'capabilities' => [
            'server_metrics',
            'application_metrics',
            'database_monitoring',
            'queue_monitoring',
            'scheduler_monitoring',
            'system_logs',
            'application_logs',
        ],
    ],

    'agent_commands' => [
        'delivery_retry_seconds' => (int) env(
            'NADI_AGENT_COMMAND_DELIVERY_RETRY_SECONDS',
            60
        ),
    ],

    'telemetry' => [
        'logs_enabled' => (bool) env(
            'NADI_TELEMETRY_LOGS_ENABLED',
            false
        ),
    ],

    'prometheus' => [
        'metrics_token' => env(
            'NADI_PROMETHEUS_METRICS_TOKEN',
            ''
        ),
    ],
];
