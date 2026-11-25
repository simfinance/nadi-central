<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrometheusQueryService
{
    private const METRICS = [
        'exporter_up' => [
            'metric' => 'nadi_prometheus_exporter_up',
            'title' => 'Prometheus Exporter',
            'description' => 'Status eksekusi exporter NADI Central.',
            'unit' => 'boolean',
            'type' => 'gauge',
        ],
        'server_cpu_usage' => [
            'metric' => 'nadi_server_cpu_usage_percent',
            'title' => 'Server CPU Usage',
            'description' => 'Persentase penggunaan CPU server.',
            'unit' => 'percent',
            'type' => 'gauge',
        ],
        'server_memory_usage' => [
            'metric' => 'nadi_server_memory_usage_percent',
            'title' => 'Server Memory Usage',
            'description' => 'Persentase penggunaan memori server.',
            'unit' => 'percent',
            'type' => 'gauge',
        ],
        'server_load_average' => [
            'metric' => 'nadi_server_load_average',
            'title' => 'Server Load Average',
            'description' => 'Load average server untuk periode 1, 5, dan 15 menit.',
            'unit' => 'load',
            'type' => 'gauge',
        ],
        'server_disk_usage' => [
            'metric' => 'nadi_server_disk_usage_percent',
            'fixed_matchers' => [
                'is_root' => 'true',
            ],
            'title' => 'Server Disk Usage',
            'description' => 'Persentase penggunaan filesystem server.',
            'unit' => 'percent',
            'type' => 'gauge',
        ],
        'server_network_receive' => [
            'metric' => 'nadi_server_network_bytes_total',
            'fixed_matchers' => [
                'direction' => 'receive',
                'is_primary' => 'true',
            ],
            'rate' => true,
            'title' => 'Server Network Receive',
            'description' => 'Laju penerimaan byte jaringan berdasarkan counter kumulatif.',
            'unit' => 'bytes_per_second',
            'type' => 'gauge',
        ],
        'server_network_transmit' => [
            'metric' => 'nadi_server_network_bytes_total',
            'fixed_matchers' => [
                'direction' => 'transmit',
                'is_primary' => 'true',
            ],
            'rate' => true,
            'title' => 'Server Network Transmit',
            'description' => 'Laju pengiriman byte jaringan berdasarkan counter kumulatif.',
            'unit' => 'bytes_per_second',
            'type' => 'gauge',
        ],
        'server_uptime' => [
            'metric' => 'nadi_server_uptime_seconds',
            'title' => 'Server Uptime',
            'description' => 'Uptime server dalam detik.',
            'unit' => 'seconds',
            'type' => 'gauge',
        ],
        'application_probe_up' => [
            'metric' => 'nadi_application_probe_up',
            'title' => 'Application Probe Status',
            'description' => 'Status probe aplikasi yang dilaporkan Agent.',
            'unit' => 'boolean',
            'type' => 'gauge',
        ],
        'application_probe_latency' => [
            'metric' => 'nadi_application_probe_latency_milliseconds',
            'title' => 'Application Probe Latency',
            'description' => 'Latensi probe aplikasi dalam milidetik.',
            'unit' => 'milliseconds',
            'type' => 'gauge',
        ],
        'telemetry_stream_age' => [
            'metric' => 'nadi_telemetry_stream_age_seconds',
            'title' => 'Telemetry Stream Age',
            'description' => 'Usia data terakhir yang diterima Central untuk stream.',
            'unit' => 'seconds',
            'type' => 'gauge',
        ],
        'telemetry_stream_up' => [
            'metric' => 'nadi_telemetry_stream_up',
            'title' => 'Telemetry Stream Status',
            'description' => 'Status freshness stream telemetry berdasarkan threshold exporter.',
            'unit' => 'boolean',
            'type' => 'gauge',
        ],
    ];

    public function metrics(): array
    {
        return array_map(
            static fn (string $key, array $metric): array => [
                'key' => $key,
                'title' => $metric['title'],
                'description' => $metric['description'],
                'unit' => $metric['unit'],
                'type' => $metric['type'],
            ],
            array_keys(self::METRICS),
            array_values(self::METRICS)
        );
    }

    public function targets(): array
    {
        return Cache::remember(
            'nadi.prometheus.query.targets.v1',
            max(1, (int) config('nadi.prometheus.cache_seconds', 10)),
            function (): array {
                $payload = $this->request(
                    '/api/v1/targets',
                    ['state' => 'active']
                );

                $targets = $payload['data']['activeTargets'] ?? [];

                if (!is_array($targets))
                {
                    throw new RuntimeException(
                        'Prometheus returned an invalid targets response.'
                    );
                }

                return array_values(array_map(
                    static fn (array $target): array => [
                        'job' => (string) ($target['labels']['job'] ?? 'unknown'),
                        'health' => (string) ($target['health'] ?? 'unknown'),
                        'last_scrape' => $target['lastScrape'] ?? null,
                        'last_error' => $target['lastError'] ?? null,
                    ],
                    array_filter($targets, 'is_array')
                ));
            }
        );
    }

    public function instantQuery(
        string $key,
        array $filters = [],
        ?CarbonImmutable $at = null
    ): array {
        $query = $this->buildQuery($key, $filters);
        $params = [
            'query' => $query,
        ];

        if ($at !== null)
        {
            $params['time'] = $at->timestamp;
        }

        return $this->cachedQuery(
            'instant',
            $key,
            $filters,
            $params,
            '/api/v1/query'
        );
    }

    public function rangeQuery(
        string $key,
        array $filters,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $stepSeconds
    ): array {
        if ($end->lessThanOrEqualTo($start))
        {
            throw new RuntimeException(
                'The Prometheus query end time must be after its start time.'
            );
        }

        $query = $this->buildQuery($key, $filters);

        return $this->cachedQuery(
            'range',
            $key,
            $filters,
            [
                'query' => $query,
                'start' => $start->timestamp,
                'end' => $end->timestamp,
                'step' => $stepSeconds . 's',
            ],
            '/api/v1/query_range'
        );
    }

    private function cachedQuery(
        string $mode,
        string $key,
        array $filters,
        array $params,
        string $path
    ): array {
        $cacheKey = 'nadi.prometheus.query.v1.'
            . hash(
                'sha256',
                json_encode(
                    [$mode, $key, $filters, $params],
                    JSON_THROW_ON_ERROR
                )
            );

        return Cache::remember(
            $cacheKey,
            max(1, (int) config('nadi.prometheus.cache_seconds', 10)),
            fn (): array => $this->request($path, $params)['data'] ?? []
        );
    }

    private function buildQuery(string $key, array $filters): string
    {
        $metric = self::METRICS[$key] ?? null;

        if ($metric === null)
        {
            throw new RuntimeException(
                'The requested metric is not in the NADI query catalog.'
            );
        }

        $matchers = $metric['fixed_matchers'] ?? [];

        foreach ([
            'server_id',
            'agent_id',
            'application_id',
        ] as $filter)
        {
            if (!isset($filters[$filter]) || $filters[$filter] === '')
            {
                continue;
            }

            $value = (string) $filters[$filter];

            if (!preg_match(
                '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
                $value
            ))
            {
                throw new RuntimeException(
                    'A Prometheus identity filter is invalid.'
                );
            }

            $matchers[$filter] = $value;
        }

        $selector = '';

        if ($matchers !== [])
        {
            $formattedMatchers = [];

            foreach ($matchers as $label => $value)
            {
                $formattedMatchers[] = $label . '="'
                    . $this->escapeLabel((string) $value)
                    . '"';
            }

            $selector = '{' . implode(',', $formattedMatchers) . '}';
        }

        $expression = $metric['metric'] . $selector;

        if (($metric['rate'] ?? false) === true)
        {
            $expression = 'rate(' . $expression . '[5m])';
        }

        if (str_starts_with($key, 'server_'))
        {
            $expression = '(' . $expression . ') and on(agent_id) '
                . '(nadi_telemetry_stream_up{stream="server_metrics"} == 1)';
        }

        return $expression;
    }

    private function request(string $path, array $params = []): array
    {
        $baseUrl = rtrim(
            (string) config(
                'nadi.prometheus.base_url',
                'http://127.0.0.1:9090'
            ),
            '/'
        );

        if ($baseUrl === '')
        {
            throw new RuntimeException(
                'The Prometheus base URL is not configured.'
            );
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout(
                max(1, (int) config('nadi.prometheus.connect_timeout_seconds', 2))
            )
            ->timeout(
                max(1, (int) config('nadi.prometheus.timeout_seconds', 5))
            )
            ->get($path, $params);

        if (!$response->successful())
        {
            throw new RuntimeException(
                'Prometheus API returned HTTP ' . $response->status() . '.'
            );
        }

        $payload = $response->json();

        if (
            !is_array($payload)
            || ($payload['status'] ?? null) !== 'success'
        )
        {
            throw new RuntimeException(
                'Prometheus API returned an unsuccessful or invalid response.'
            );
        }

        return $payload;
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(
            ["\\", '"', "\n"],
            ["\\\\", '\\"', "\\n"],
            $value
        );
    }
}
