<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsController extends Controller
{
    private const CACHE_SECONDS = 5;

    private const STREAM_STALE_SECONDS = 120;

    private const STREAM_OFFLINE_SECONDS = 300;

    private const PROBES = [
        'health',
        'info',
        'database',
        'metrics',
        'queue',
        'scheduler',
    ];

    public function __invoke(Request $request): Response
    {
        $expectedToken = (string) config(
            'nadi.prometheus.metrics_token',
            ''
        );

        $providedToken = (string) $request->bearerToken();

        if ($expectedToken === '')
        {
            return response(
                'Prometheus metrics endpoint is not configured.\n',
                503,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }

        if (
            $providedToken === ''
            || !hash_equals($expectedToken, $providedToken)
        )
        {
            return response(
                'Unauthorized.\n',
                401,
                [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'WWW-Authenticate' => 'Bearer',
                ]
            );
        }

        $body = Cache::remember(
            'nadi.prometheus.metrics.v1',
            self::CACHE_SECONDS,
            fn (): string => $this->renderMetrics()
        );

        return response(
            $body,
            200,
            [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function renderMetrics(): string
    {
        $lines = [];

        $this->family(
            $lines,
            'nadi_prometheus_exporter_up',
            'gauge',
            'Whether the NADI Central Prometheus exporter completed successfully.'
        );

        $this->sample(
            $lines,
            'nadi_prometheus_exporter_up',
            [],
            1
        );

        $this->family(
            $lines,
            'nadi_telemetry_stream_last_received_timestamp_seconds',
            'gauge',
            'Unix timestamp when Central last received a record for this telemetry stream.'
        );

        $this->family(
            $lines,
            'nadi_telemetry_stream_age_seconds',
            'gauge',
            'Age in seconds of the latest received record for this telemetry stream.'
        );

        $this->family(
            $lines,
            'nadi_telemetry_stream_up',
            'gauge',
            'Whether a telemetry stream received data within the offline threshold.'
        );

        $this->family(
            $lines,
            'nadi_telemetry_stream_stale',
            'gauge',
            'Whether a telemetry stream is stale but has not crossed the offline threshold.'
        );

        $this->family(
            $lines,
            'nadi_server_cpu_usage_percent',
            'gauge',
            'Latest server CPU usage percentage reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_memory_usage_percent',
            'gauge',
            'Latest server memory usage percentage reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_memory_bytes',
            'gauge',
            'Latest server memory size in bytes, labeled by memory state.'
        );

        $this->family(
            $lines,
            'nadi_server_load_average',
            'gauge',
            'Latest server load average, labeled by averaging period.'
        );

        $this->family(
            $lines,
            'nadi_server_uptime_seconds',
            'gauge',
            'Latest server uptime in seconds.'
        );

        $this->family(
            $lines,
            'nadi_server_disk_usage_percent',
            'gauge',
            'Latest disk usage percentage reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_disk_bytes',
            'gauge',
            'Latest disk capacity in bytes, labeled by mount point and state.'
        );

        $this->family(
            $lines,
            'nadi_server_network_bytes_total',
            'counter',
            'Cumulative network bytes reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_network_packets_total',
            'counter',
            'Cumulative network packets reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_network_dropped_total',
            'counter',
            'Cumulative network packets dropped reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_network_errors_total',
            'counter',
            'Cumulative network errors reported by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_server_nginx_process_up',
            'gauge',
            'Whether the Nginx process is reported as running by NADI Agent.'
        );

        $this->family(
            $lines,
            'nadi_application_probe_up',
            'gauge',
            'Whether an application monitoring probe is reported healthy.'
        );

        $this->family(
            $lines,
            'nadi_application_probe_latency_milliseconds',
            'gauge',
            'Latest application monitoring probe latency in milliseconds.'
        );

        $streams = $this->latestStreams();

        foreach ($streams as $stream)
        {
            $labels = [
                'agent_id' => (string) $stream->agent_id,
                'server_id' => (string) $stream->server_id,
                'hostname' => (string) $stream->hostname,
                'stream' => (string) $stream->stream_name,
            ];

            if ($stream->application_id !== null)
            {
                $labels['application_id'] = (string) $stream->application_id;
                $labels['application_name'] = (string) ($stream->application_name ?? '');
                $labels['environment'] = (string) ($stream->environment ?? '');
            }

            $this->appendStreamMetrics(
                $lines,
                $labels,
                $stream
            );

            if (
                $stream->stream_name === 'server_metrics'
                && is_string($stream->data)
            )
            {
                $this->appendServerMetrics(
                    $lines,
                    $labels,
                    $stream->data
                );
            }

            if (
                $stream->stream_name === 'application_metrics'
                && is_string($stream->data)
            )
            {
                $this->appendApplicationMetrics(
                    $lines,
                    $labels,
                    $stream->data
                );
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function latestStreams()
    {
        $latestRecords = DB::table('nadi_telemetry_records')
            ->select('stream_id')
            ->selectRaw('MAX(observed_at) AS latest_observed_at')
            ->groupBy('stream_id');

        return DB::table('nadi_telemetry_streams as streams')
            ->join('nadi_agents as agents', 'agents.id', '=', 'streams.agent_id')
            ->join('nadi_servers as servers', 'servers.id', '=', 'agents.server_id')
            ->leftJoin(
                'nadi_applications as applications',
                'applications.id',
                '=',
                'streams.application_id'
            )
            ->leftJoinSub(
                $latestRecords,
                'latest_records',
                function ($join): void
                {
                    $join->on(
                        'latest_records.stream_id',
                        '=',
                        'streams.id'
                    );
                }
            )
            ->leftJoin(
                'nadi_telemetry_records as records',
                function ($join): void
                {
                    $join->on(
                        'records.stream_id',
                        '=',
                        'streams.id'
                    )->on(
                        'records.observed_at',
                        '=',
                        'latest_records.latest_observed_at'
                    );
                }
            )
            ->where('agents.installation_state', 'installed')
            ->whereIn(
                'streams.stream_name',
                [
                    'server_metrics',
                    'application_metrics',
                ]
            )
            ->orderBy('servers.hostname')
            ->get([
                'streams.agent_id',
                'streams.application_id',
                'streams.stream_name',
                'streams.last_received_at',
                'agents.server_id',
                'servers.hostname',
                'applications.application_name',
                'applications.environment',
                'records.data',
            ]);
    }

    private function appendStreamMetrics(
        array &$lines,
        array $labels,
        object $stream
    ): void
    {
        if (!$stream->last_received_at)
        {
            return;
        }

        $lastReceivedAt = CarbonImmutable::parse(
            $stream->last_received_at,
            'UTC'
        );

        $lastReceivedTimestamp = $lastReceivedAt->getTimestamp();
        $age = max(
            0,
            CarbonImmutable::now('UTC')->getTimestamp() - $lastReceivedTimestamp
        );

        $this->sample(
            $lines,
            'nadi_telemetry_stream_last_received_timestamp_seconds',
            $labels,
            $lastReceivedTimestamp
        );

        $this->sample(
            $lines,
            'nadi_telemetry_stream_age_seconds',
            $labels,
            $age
        );

        $this->sample(
            $lines,
            'nadi_telemetry_stream_up',
            $labels,
            $age <= self::STREAM_OFFLINE_SECONDS ? 1 : 0
        );

        $this->sample(
            $lines,
            'nadi_telemetry_stream_stale',
            $labels,
            $age > self::STREAM_STALE_SECONDS
                && $age <= self::STREAM_OFFLINE_SECONDS
                    ? 1
                    : 0
        );
    }

    private function appendServerMetrics(
        array &$lines,
        array $labels,
        string $encodedData
    ): void
    {
        $record = json_decode(
            $encodedData,
            true
        );

        $data = is_array($record)
            ? ($record['data'] ?? [])
            : [];

        if (!is_array($data))
        {
            return;
        }

        $cpuUsage = $data['cpu']['usage_percent'] ?? null;

        if (is_numeric($cpuUsage))
        {
            $this->sample(
                $lines,
                'nadi_server_cpu_usage_percent',
                $labels,
                $cpuUsage
            );
        }

        $memory = $data['memory'] ?? [];

        if (is_array($memory))
        {
            if (is_numeric($memory['usage_percent'] ?? null))
            {
                $this->sample(
                    $lines,
                    'nadi_server_memory_usage_percent',
                    $labels,
                    $memory['usage_percent']
                );
            }

            foreach ([
                'total_bytes' => 'total',
                'used_bytes' => 'used',
                'available_bytes' => 'available',
            ] as $field => $state)
            {
                if (is_numeric($memory[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_memory_bytes',
                        $labels + ['state' => $state],
                        $memory[$field]
                    );
                }
            }
        }

        $loadAverage = $data['load_average'] ?? [];

        if (is_array($loadAverage))
        {
            foreach ([
                'one_minute' => '1m',
                'five_minutes' => '5m',
                'fifteen_minutes' => '15m',
            ] as $field => $period)
            {
                if (is_numeric($loadAverage[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_load_average',
                        $labels + ['period' => $period],
                        $loadAverage[$field]
                    );
                }
            }
        }

        if (is_numeric($data['uptime_seconds'] ?? null))
        {
            $this->sample(
                $lines,
                'nadi_server_uptime_seconds',
                $labels,
                $data['uptime_seconds']
            );
        }

        foreach (($data['disks'] ?? []) as $disk)
        {
            if (!is_array($disk))
            {
                continue;
            }

            $diskLabels = $labels + [
                'mount_point' => (string) ($disk['mount_point'] ?? 'unknown'),
                'filesystem' => (string) ($disk['filesystem'] ?? 'unknown'),
            ];

            if (is_numeric($disk['usage_percent'] ?? null))
            {
                $this->sample(
                    $lines,
                    'nadi_server_disk_usage_percent',
                    $diskLabels,
                    $disk['usage_percent']
                );
            }

            foreach ([
                'total_bytes' => 'total',
                'used_bytes' => 'used',
                'available_bytes' => 'available',
            ] as $field => $state)
            {
                if (is_numeric($disk[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_disk_bytes',
                        $diskLabels + ['state' => $state],
                        $disk[$field]
                    );
                }
            }
        }

        foreach (($data['network_interfaces'] ?? []) as $network)
        {
            if (!is_array($network))
            {
                continue;
            }

            $networkLabels = $labels + [
                'interface' => (string) ($network['interface'] ?? 'unknown'),
            ];

            foreach ([
                'receive_bytes' => 'receive',
                'transmit_bytes' => 'transmit',
            ] as $field => $direction)
            {
                if (is_numeric($network[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_network_bytes_total',
                        $networkLabels + ['direction' => $direction],
                        $network[$field]
                    );
                }
            }

            foreach ([
                'receive_packets' => 'receive',
                'transmit_packets' => 'transmit',
            ] as $field => $direction)
            {
                if (is_numeric($network[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_network_packets_total',
                        $networkLabels + ['direction' => $direction],
                        $network[$field]
                    );
                }
            }

            foreach ([
                'receive_dropped' => 'receive',
                'transmit_dropped' => 'transmit',
            ] as $field => $direction)
            {
                if (is_numeric($network[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_network_dropped_total',
                        $networkLabels + ['direction' => $direction],
                        $network[$field]
                    );
                }
            }

            foreach ([
                'receive_errors' => 'receive',
                'transmit_errors' => 'transmit',
            ] as $field => $direction)
            {
                if (is_numeric($network[$field] ?? null))
                {
                    $this->sample(
                        $lines,
                        'nadi_server_network_errors_total',
                        $networkLabels + ['direction' => $direction],
                        $network[$field]
                    );
                }
            }
        }

        $nginx = $data['nginx'] ?? [];

        if (is_array($nginx) && isset($nginx['process_status']))
        {
            $this->sample(
                $lines,
                'nadi_server_nginx_process_up',
                $labels,
                $nginx['process_status'] === 'running' ? 1 : 0
            );
        }
    }

    private function appendApplicationMetrics(
        array &$lines,
        array $labels,
        string $encodedData
    ): void
    {
        $record = json_decode(
            $encodedData,
            true
        );

        $data = is_array($record)
            ? ($record['data'] ?? [])
            : [];

        if (!is_array($data))
        {
            return;
        }

        foreach (self::PROBES as $probe)
        {
            $result = $data[$probe] ?? null;

            if (!is_array($result))
            {
                continue;
            }

            $status = strtolower(
                (string) ($result['status'] ?? 'unknown')
            );

            $probeLabels = $labels + [
                'probe' => $probe,
            ];

            $this->sample(
                $lines,
                'nadi_application_probe_up',
                $probeLabels,
                in_array(
                    $status,
                    ['available', 'ok', 'healthy', 'up'],
                    true
                ) ? 1 : 0
            );

            if (is_numeric($result['latency_ms'] ?? null))
            {
                $this->sample(
                    $lines,
                    'nadi_application_probe_latency_milliseconds',
                    $probeLabels,
                    $result['latency_ms']
                );
            }
        }
    }

    private function family(
        array &$lines,
        string $name,
        string $type,
        string $help
    ): void
    {
        $lines[] = '# HELP ' . $name . ' ' . $help;
        $lines[] = '# TYPE ' . $name . ' ' . $type;
    }

    private function sample(
        array &$lines,
        string $name,
        array $labels,
        mixed $value
    ): void
    {
        if (!is_numeric($value) || !is_finite((float) $value))
        {
            return;
        }

        $labelSet = '';

        if ($labels !== [])
        {
            $formatted = [];

            foreach ($labels as $key => $labelValue)
            {
                $formatted[] = $key . '="' . $this->escapeLabel(
                    (string) $labelValue
                ) . '"';
            }

            $labelSet = '{' . implode(',', $formatted) . '}';
        }

        $lines[] = $name
            . $labelSet
            . ' '
            . sprintf('%.10g', (float) $value);
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(
            ["\\", "\n", '"'],
            ["\\\\", "\\n", '\\"'],
            $value
        );
    }
}
