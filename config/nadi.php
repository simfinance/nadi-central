<?php

return [
    'activation' => [
        'key_id' => env(
            'NADI_ACTIVATION_KEY_ID',
            'nadi-central-2026-01'
        ),

        'private_key_path' => env(
            'NADI_ACTIVATION_PRIVATE_KEY',
            '/etc/nadi-central/keys/activation-ed25519-seed.key'
        ),

        'public_key_path' => env(
            'NADI_ACTIVATION_PUBLIC_KEY',
            '/etc/nadi-central/keys/activation-ed25519-public.key'
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

    'alertmanager' => [
        'webhook_token' => env(
            'NADI_ALERTMANAGER_WEBHOOK_TOKEN',
            ''
        ),
    ],

    'grafana' => [
        'internal_url' => env('NADI_GRAFANA_INTERNAL_URL', 'http://127.0.0.1:3000'),
        'public_path' => env('NADI_GRAFANA_PUBLIC_PATH', '/grafana/'),
        'dashboard_uid' => env('NADI_GRAFANA_DASHBOARD_UID', ''),
        'org_id' => (int) env('NADI_GRAFANA_ORG_ID', 1),
    ],

    'prometheus' => [
        'metrics_token' => env(
            'NADI_PROMETHEUS_METRICS_TOKEN',
            ''
        ),

        'base_url' => env(
            'NADI_PROMETHEUS_URL',
            'http://127.0.0.1:9090'
        ),

        'connect_timeout_seconds' => (int) env(
            'NADI_PROMETHEUS_CONNECT_TIMEOUT_SECONDS',
            2
        ),

        'timeout_seconds' => (int) env(
            'NADI_PROMETHEUS_TIMEOUT_SECONDS',
            5
        ),

        'cache_seconds' => (int) env(
            'NADI_PROMETHEUS_QUERY_CACHE_SECONDS',
            10
        ),

        'max_range_hours' => (int) env(
            'NADI_PROMETHEUS_MAX_RANGE_HOURS',
            168
        ),

        'max_data_points' => (int) env(
            'NADI_PROMETHEUS_MAX_DATA_POINTS',
            11000
        ),
    ],
];
