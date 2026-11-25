<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard analisa telemetry NADI.
 *
 * Sumber data:
 *  - nadi_telemetry_records  (kolom JSON `data` => {"data": {...}, "metric_set": null})
 *      stream "server_metrics"      : cpu, memory, disks, load_average, network_interfaces, uptime_seconds, nginx
 *      stream "application_metrics" : probe health/info/database/metrics/queue/scheduler (status, latency_ms, error_code)
 *  - nadi_telemetry_batches          : throughput, duplikat, penolakan, dan lag ingest
 *  - nadi_telemetry_streams / gaps   : integritas sequence
 *  - nadi_telemetry_collector_states : kondisi collector di agent
 *
 * Semua timestamp di database diasumsikan UTC dan dikirim ke browser sebagai ISO-8601 "Z".
 */
class TelemetryController extends Controller
{
    /** key => [window detik, ukuran bucket detik, label] */
    private const RANGES = [
        '1h'  => [3600,   60,   '1 Hour'],
        '6h'  => [21600,  300,  '6 Hours'],
        '24h' => [86400,  900,  '24 Hours'],
        '7d'  => [604800, 3600, '7 Days'],
    ];

    private const DEFAULT_RANGE = '24h';

    /** Batas kesegaran stream (detik sejak data terakhir diterima). */
    private const STALE_AFTER   = 120;
    private const OFFLINE_AFTER = 300;

    private const PROBES = ['health', 'info', 'database', 'metrics', 'queue', 'scheduler'];

    private const PROBE_OK = ['available', 'ok', 'healthy', 'up'];

    /** Filesystem pseudo yang disembunyikan dari tampilan disk. */
    private const PSEUDO_FS = ['squashfs', 'tmpfs', 'devtmpfs', 'overlay', 'iso9660', 'ramfs'];

    /** Ringkasan seluruh server & aplikasi terdaftar (jumlahnya dinamis). */
    public function index(Request $request): View
    {
        [$rangeKey] = $this->resolveRange($request);

        return view('management.telemetry', [
            'payload' => $this->fleetPayload($rangeKey),
            'ranges'  => $this->rangeLabels(),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        [$rangeKey] = $this->resolveRange($request);

        return response()->json($this->fleetPayload($rangeKey));
    }

    /** Detail telemetry satu server (beserta aplikasi di dalamnya). */
    public function show(Request $request, string $agent): View
    {
        abort_unless($this->loadAgents()->contains('id', $agent), 404);

        [$rangeKey] = $this->resolveRange($request);

        return view('management.telemetry-detail', [
            'payload' => $this->buildPayload($rangeKey, $agent),
            'ranges'  => $this->rangeLabels(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$rangeKey] = $this->resolveRange($request);

        return response()->json($this->buildPayload($rangeKey, $request->query('agent')));
    }


    /**
     * Histori canonical untuk halaman Infrastructure.
     *
     * Data diambil melalui agregasi yang sama dengan halaman Telemetry,
     * sehingga kedua halaman memakai record, bucket, dan pemilihan resource
     * yang identik.
     */
    public function infrastructureData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:1h,6h,24h,7d'],
            'server_id' => ['nullable', 'uuid'],
            'application_id' => ['nullable', 'uuid'],
        ]);

        [$rangeKey] = $this->resolveRange($request);

        $window = self::RANGES[$rangeKey][0];
        $bucket = self::RANGES[$rangeKey][1];
        $agents = $this->loadAgents();

        if (!empty($validated['server_id']))
        {
            $agents = $agents->where('server_id', $validated['server_id'])->values();
        }

        $base = $this->basePayload($rangeKey, $agents);
        $metrics = [
            'cpu' => [],
            'memory' => [],
            'disk' => [],
            'rx' => [],
            'tx' => [],
            'probe' => [],
            'latency' => [],
            'age' => [],
            'up' => [],
        ];
        $probeAvailability = [];

        foreach ($agents as $agent)
        {
            $payload = $this->infrastructureAgentSections($agent, $window, $bucket);
            $server = $payload['server'];

            if ($server)
            {
                $labels = [
                    'hostname' => (string) $agent->hostname,
                    'server_id' => (string) $agent->server_id,
                    'agent_id' => (string) $agent->id,
                    'server_state' => $payload['server_state'],
                ];

                foreach ([
                    'cpu' => ['series' => 'cpu_avg', 'current' => $server['cpu']['usage'] ?? null],
                    'memory' => ['series' => 'mem_avg', 'current' => $server['memory']['usage_percent'] ?? null],
                    'disk' => ['series' => 'disk_pct', 'current' => $server['disks'][0]['usage'] ?? null],
                    'rx' => ['series' => 'rx_rate', 'current' => null],
                    'tx' => ['series' => 'tx_rate', 'current' => null],
                ] as $key => $metric)
                {
                    $metricLabels = $labels;

                    if ($metric['current'] !== null)
                    {
                        $metricLabels['current_value'] = (string) $metric['current'];
                    }

                    $metrics[$key][] = $this->seriesResult(
                        $metricLabels,
                        $server['series']['t'] ?? [],
                        $server['series'][$metric['series']] ?? []
                    );
                }
            }

            foreach ($payload['streams'] as $stream)
            {
                $streamLabels = [
                    'hostname' => (string) $agent->hostname,
                    'server_id' => (string) $agent->server_id,
                    'agent_id' => (string) $agent->id,
                    'stream' => (string) $stream['name'],
                ];

                if ($stream['name'] === 'application_metrics')
                {
                    $application = collect($payload['applications'])
                        ->firstWhere('stream_id', $stream['id']);

                    if (!$application)
                    {
                        continue;
                    }

                    if (
                        !empty($validated['application_id'])
                        && $application['application_id'] !== $validated['application_id']
                    )
                    {
                        continue;
                    }

                    $streamLabels['application_id'] = (string) $application['application_id'];
                    $streamLabels['application_name'] = (string) $application['name'];
                    $streamLabels['environment'] = (string) ($application['environment'] ?? '');
                }

                $age = $stream['age_seconds'];
                $timestamp = $base['generated_at'];

                if ($age !== null)
                {
                    $metrics['age'][] = $this->seriesResult(
                        $streamLabels,
                        [$timestamp],
                        [$age]
                    );

                    $metrics['up'][] = $this->seriesResult(
                        $streamLabels,
                        [$timestamp],
                        [$age <= self::OFFLINE_AFTER ? 1 : 0]
                    );
                }
            }

            foreach ($payload['applications'] as $application)
            {
                if (
                    !empty($validated['application_id'])
                    && $application['application_id'] !== $validated['application_id']
                )
                {
                    continue;
                }

                foreach ($application['probes'] as $probe => $details)
                {
                    $labels = [
                        'hostname' => (string) $agent->hostname,
                        'server_id' => (string) $agent->server_id,
                        'agent_id' => (string) $agent->id,
                        'application_id' => (string) $application['application_id'],
                        'application_name' => (string) $application['name'],
                        'environment' => (string) ($application['environment'] ?? ''),
                        'probe' => (string) $probe,
                        'availability' => $details['availability'] !== null
                            ? (string) $details['availability']
                            : '',
                        'current_up' => in_array($details['status'], self::PROBE_OK, true) ? '1' : '0',
                    ];

                    $metrics['latency'][] = $this->seriesResult(
                        $labels,
                        $application['series']['t'] ?? [],
                        $application['series'][$probe] ?? []
                    );

                    $metrics['probe'][] = $this->seriesResult(
                        $labels,
                        $application['series']['t'] ?? [],
                        $application['series'][$probe . '_up'] ?? []
                    );

                    $probeAvailability[] = [
                        'application_id' => (string) $application['application_id'],
                        'application_name' => (string) $application['name'],
                        'probe' => (string) $probe,
                        'availability' => $details['availability'],
                    ];
                }
            }
        }

        return response()->json([
            'range' => $base['range'],
            'generated_at' => $base['generated_at'],
            'metrics' => $metrics,
            'probe_availability' => $probeAvailability,
        ]);
    }


    private function infrastructureAgentSections(
        object $agent,
        int $window,
        int $bucket
    ): array {
        $streams = DB::table('nadi_telemetry_streams as st')
            ->leftJoin('nadi_applications as ap', 'ap.id', '=', 'st.application_id')
            ->where('st.agent_id', $agent->id)
            ->get([
                'st.id',
                'st.application_id',
                'st.stream_name',
                'st.last_observed_at',
                'st.last_received_at',
                'ap.application_name',
                'ap.environment',
                'ap.application_key',
                'ap.monitoring_status',
                'ap.activation_status',
                'ap.framework',
            ]);

        $now = CarbonImmutable::now('UTC');
        $lastSeen = $streams
            ->map(fn ($stream) => $stream->last_observed_at
                ? CarbonImmutable::parse($stream->last_observed_at, 'UTC')
                : null)
            ->filter()
            ->max();

        $end = $now;

        if ($lastSeen && $lastSeen->lt($now->subSeconds($window)))
        {
            $end = $lastSeen;
        }

        $start = $end->subSeconds($window);
        $serverStream = $streams->firstWhere('stream_name', 'server_metrics');
        $appStreams = $streams
            ->where('stream_name', 'application_metrics')
            ->values();

        $server = $serverStream
            ? $this->serverSection($serverStream, $start, $end, $bucket)
            : null;

        $applications = $appStreams
            ->map(fn ($stream) => $this->applicationSection($stream, $start, $end, $bucket))
            ->all();

        $streamRows = $streams->map(function ($stream) use ($now) {
            $lastReceived = $stream->last_received_at
                ? CarbonImmutable::parse($stream->last_received_at, 'UTC')
                : null;
            $age = $lastReceived
                ? max(0, $now->getTimestamp() - $lastReceived->getTimestamp())
                : null;
            $state = $age === null
                ? 'offline'
                : ($age > self::OFFLINE_AFTER
                    ? 'offline'
                    : ($age > self::STALE_AFTER ? 'stale' : 'live'));

            return [
                'id' => $stream->id,
                'name' => $stream->stream_name,
                'application_id' => $stream->application_id,
                'application_name' => $stream->application_name,
                'age_seconds' => $age,
                'state' => $state,
            ];
        })->values()->all();

        $streamOrder = ['live' => 0, 'stale' => 1, 'offline' => 2];
        $serverState = 'offline';

        if ($streamRows !== [])
        {
            $serverState = 'live';

            foreach ($streamRows as $stream)
            {
                if ($streamOrder[$stream['state']] > $streamOrder[$serverState])
                {
                    $serverState = $stream['state'];
                }
            }
        }

        return [
            'server' => $server,
            'applications' => $applications,
            'streams' => $streamRows,
            'server_state' => $serverState,
        ];
    }

    private function seriesResult(array $labels, array $timestamps, array $values): array
    {
        $points = [];

        foreach ($timestamps as $index => $timestamp)
        {
            $value = $values[$index] ?? null;

            if ($value === null || !is_numeric($value))
            {
                continue;
            }

            $points[] = [
                CarbonImmutable::parse($timestamp, 'UTC')->getTimestamp(),
                (string) $value,
            ];
        }

        return [
            'metric' => $labels,
            'values' => $points,
        ];
    }

    /** Detail satu record mentah (dimuat saat baris recent record dibuka). */
    public function record(string $id): JsonResponse
    {
        $row = DB::table('nadi_telemetry_records as r')
            ->join('nadi_telemetry_streams as s', 's.id', '=', 'r.stream_id')
            ->where('r.id', $id)
            ->select('r.id', 'r.sequence', 'r.observed_at', 'r.collected_at', 'r.data', 's.stream_name')
            ->first();

        abort_if(! $row, 404);

        return response()->json([
            'id'           => $row->id,
            'stream'       => $row->stream_name,
            'sequence'     => (int) $row->sequence,
            'observed_at'  => $this->iso($row->observed_at),
            'collected_at' => $this->iso($row->collected_at),
            'data'         => json_decode($row->data, true),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Payload                                                            */
    /* ------------------------------------------------------------------ */

    private function resolveRange(Request $request): array
    {
        $key = $request->query('range', self::DEFAULT_RANGE);

        if (! isset(self::RANGES[$key])) {
            $key = self::DEFAULT_RANGE;
        }

        return [$key, self::RANGES[$key][0], self::RANGES[$key][1]];
    }

    private function buildPayload(string $rangeKey, ?string $agentId): array
    {
        $window = self::RANGES[$rangeKey][0];
        $bucket = self::RANGES[$rangeKey][1];

        $agents = $this->loadAgents();

        $base = $this->basePayload($rangeKey, $agents);

        if ($agents->isEmpty()) {
            return $base + ['empty' => true];
        }

        $agent = $agents->firstWhere('id', $agentId) ?? $agents->first();

        return $this->agentPayload($agent, $base, $window, $bucket);
    }

    private function rangeLabels(): array
    {
        return collect(self::RANGES)->map(fn ($r) => $r[2])->all();
    }

    private function loadAgents()
    {
        return DB::table('nadi_agents as a')
            ->join('nadi_servers as s', 's.id', '=', 'a.server_id')
            ->where('a.installation_state', 'installed')
            ->orderBy('s.hostname')
            ->get([
                'a.id', 'a.server_id', 'a.status', 'a.agent_version', 'a.protocol_version', 'a.installed_at',
                'a.last_control_at', 'a.last_heartbeat_at',
                's.hostname', 's.ip_address', 's.os_name', 's.os_version', 's.architecture',
            ]);
    }

    private function basePayload(string $rangeKey, $agents): array
    {
        return [
            'generated_at' => $this->iso(CarbonImmutable::now('UTC')),
            'range'        => ['key' => $rangeKey, 'seconds' => self::RANGES[$rangeKey][0], 'bucket' => self::RANGES[$rangeKey][1]],
            'agents'       => $agents->map(fn ($a) => ['id' => $a->id, 'hostname' => $a->hostname])->values()->all(),
        ];
    }

    /** Payload lengkap satu agent (dipakai halaman detail dan ringkasan fleet). */
    private function agentPayload(object $agent, array $base, int $window, int $bucket): array
    {
        $streams = DB::table('nadi_telemetry_streams as st')
            ->leftJoin('nadi_applications as ap', 'ap.id', '=', 'st.application_id')
            ->where('st.agent_id', $agent->id)
            ->get([
                'st.id', 'st.application_id', 'st.stream_name', 'st.source',
                'st.last_contiguous_sequence', 'st.max_sequence_seen',
                'st.last_observed_at', 'st.last_received_at',
                'ap.application_name', 'ap.environment', 'ap.application_key',
                'ap.monitoring_status', 'ap.activation_status', 'ap.framework',
            ]);

        $serverStream = $streams->firstWhere('stream_name', 'server_metrics');
        $appStreams   = $streams->where('stream_name', 'application_metrics')->values();

        // Jangkar waktu: sekarang, kecuali agent sudah lama tidak mengirim data.
        $now        = CarbonImmutable::now('UTC');
        $lastSeen   = $streams->map(fn ($s) => $s->last_observed_at ? CarbonImmutable::parse($s->last_observed_at, 'UTC') : null)
            ->filter()->max();
        $anchored   = false;
        $end        = $now;

        if ($lastSeen && $lastSeen->lt($now->subSeconds($window))) {
            $end      = $lastSeen;
            $anchored = true;
        }

        $start = $end->subSeconds($window);

        $server   = $serverStream ? $this->serverSection($serverStream, $start, $end, $bucket) : null;
        $apps     = $appStreams->map(fn ($s) => $this->applicationSection($s, $start, $end, $bucket))->all();
        $pipeline = $this->pipelineSection($agent->id, $streams, $start, $end, $bucket, $now);
        $collectors = $this->collectorSection($agent->id);
        $recent   = $this->recentRecords($agent->id);

        $payload = $base + [
            'empty'    => false,
            'anchored' => $anchored,
            'window'   => ['start' => $this->iso($start), 'end' => $this->iso($end)],
            'agent'    => [
                'id'               => $agent->id,
                'status'           => $agent->status,
                'version'          => $agent->agent_version,
                'protocol'         => $agent->protocol_version,
                'installed_at'     => $this->iso($agent->installed_at),
                'last_control_at'  => $this->iso($agent->last_control_at),
                'last_heartbeat_at'=> $this->iso($agent->last_heartbeat_at),
                'hostname'         => $agent->hostname,
                'ip_address'       => $agent->ip_address,
                'os'               => trim(($agent->os_name ?? '') . ' ' . ($agent->os_version ?? '')),
                'architecture'     => $agent->architecture,
            ],
            'server'     => $server,
            'applications' => $apps,
            'pipeline'   => $pipeline,
            'collectors' => $collectors,
            'recent'     => $recent,
        ];

        $payload['insights'] = $this->insights($payload);

        return $payload;
    }

    /* ------------------------------------------------------------------ */
    /*  Fleet overview                                                     */
    /* ------------------------------------------------------------------ */

    private function fleetPayload(string $rangeKey): array
    {
        $window = self::RANGES[$rangeKey][0];
        $bucket = self::RANGES[$rangeKey][1];
        $agents = $this->loadAgents();
        $base   = $this->basePayload($rangeKey, $agents);
        $now    = CarbonImmutable::now('UTC');

        // Semua aplikasi terdaftar, termasuk yang belum mengirim telemetry.
        $registered = DB::table('nadi_applications as ap')
            ->join('nadi_agents as a', 'a.id', '=', 'ap.agent_id')
            ->join('nadi_servers as s', 's.id', '=', 'a.server_id')
            ->whereNull('ap.unregistered_at')
            ->where('a.installation_state', 'installed')
            ->orderBy('ap.application_name')
            ->get([
                'ap.id', 'ap.agent_id', 'ap.application_name', 'ap.application_key', 'ap.environment', 'ap.framework',
                'ap.registration_status', 'ap.activation_status', 'ap.monitoring_status', 's.hostname',
            ]);

        $servers = [];
        $alerts  = [];
        $sections = []; // application_key => [section, agentId, state]

        foreach ($agents as $agent) {
            $p = $this->agentPayload($agent, $base, $window, $bucket);

            $srv    = $p['server'];
            $order  = ['live' => 0, 'stale' => 1, 'offline' => 2];
            $state  = 'offline';

            if (! empty($p['pipeline']['streams'])) {
                $state = 'live';
                foreach ($p['pipeline']['streams'] as $st) {
                    if ($order[$st['state']] > $order[$state]) {
                        $state = $st['state'];
                    }
                }
            }

            $cnt = ['crit' => 0, 'warn' => 0];
            $top = null;
            foreach ($p['insights'] as $in) {
                if (isset($cnt[$in['level']])) {
                    $cnt[$in['level']]++;
                    $top = $top ?? $in['title'];
                    $alerts[] = $in + ['server_id' => $agent->id, 'server' => $agent->hostname];
                }
            }

            $root = $srv['disks'][0] ?? null;

            $servers[] = [
                'id'        => $agent->id,
                'hostname'  => $agent->hostname,
                'ip'        => $agent->ip_address,
                'os'        => $p['agent']['os'],
                'arch'      => $agent->architecture,
                'agent_version' => $agent->agent_version,
                'agent_status'  => $agent->status,
                'state'     => $state,
                'level'     => ($state === 'offline' || $cnt['crit']) ? 'crit' : (($state === 'stale' || $cnt['warn']) ? 'warn' : 'ok'),
                'anchored'  => $p['anchored'],
                'last_seen' => $srv['observed_at'] ?? ($p['pipeline']['streams'][0]['last_received_at'] ?? null),
                'uptime'    => $srv['uptime'] ?? null,
                'cpu'       => $srv['cpu']['usage'] ?? null,
                'cores'     => $srv['cpu']['cores'] ?? null,
                'mem'       => $srv['memory']['usage_percent'] ?? null,
                'mem_used'  => $srv['memory']['used_bytes'] ?? null,
                'mem_total' => $srv['memory']['total_bytes'] ?? null,
                'disk'      => $root['usage'] ?? null,
                'disk_free' => $root['available'] ?? null,
                'disk_eta'  => $srv['disk_trend']['eta_days'] ?? null,
                'load'      => $srv['load']['one_minute'] ?? null,
                'load_pc'   => $srv['load']['per_core'] ?? null,
                'spark'     => $srv ? ['cpu' => $srv['series']['cpu_avg'], 'mem' => $srv['series']['mem_avg']] : null,
                'apps'      => array_map(fn ($a) => $a['name'], $p['applications']),
                'issues'    => $cnt,
                'top_issue' => $top,
                'records'   => $p['pipeline']['totals']['records'],
                'rejected'  => $p['pipeline']['totals']['rejected'],
            ];

            foreach ($p['applications'] as $a) {
                $sections[$a['key']] = $a;
            }
        }

        $apps = [];

        foreach ($registered as $r) {
            $a   = $sections[$r->application_key] ?? null;
            $okN = 0;
            $probes = [];
            $state  = 'pending';
            $avail  = null;
            $lat    = null;
            $spark  = null;
            $last   = null;

            if ($a) {
                foreach ($a['probes'] as $name => $pr) {
                    $probes[$name] = $pr['status'];
                    $okN += in_array($pr['status'], self::PROBE_OK, true) ? 1 : 0;
                }

                $last  = $a['observed_at'];
                $age   = $last ? max(0, $now->getTimestamp() - CarbonImmutable::parse($last, 'UTC')->getTimestamp()) : null;
                $state = $age === null || $age > self::OFFLINE_AFTER ? 'offline' : ($age > self::STALE_AFTER ? 'stale' : 'live');
                $vals  = array_filter(array_column($a['probes'], 'availability'), fn ($v) => $v !== null);
                $avail = $vals ? round(array_sum($vals) / count($vals), 1) : null;
                $lat   = $a['probes']['health']['latency_avg'] ?? null;
                $spark = $a['series']['health'];
            }

            $total = count(self::PROBES);

            $apps[] = [
                'id'          => $r->id,
                'name'        => $r->application_name,
                'key'         => $r->application_key,
                'environment' => $r->environment,
                'framework'   => $r->framework,
                'server_id'   => $r->agent_id,
                'server'      => $r->hostname,
                'registration'=> $r->registration_status,
                'activation'  => $r->activation_status,
                'monitoring'  => $r->monitoring_status,
                'state'       => $state,
                'level'       => $state === 'pending' ? 'idle' : ($state === 'offline' ? 'crit' : ($okN === $total ? 'ok' : 'warn')),
                'probes'      => $probes,
                'probes_ok'   => $okN,
                'probes_total'=> $total,
                'availability'=> $avail,
                'latency_avg' => $lat,
                'spark'       => $spark,
                'last_probe'  => $last,
            ];
        }

        $rank = ['crit' => 0, 'warn' => 1];
        usort($alerts, fn ($x, $y) => $rank[$x['level']] <=> $rank[$y['level']]);

        $live  = array_filter($servers, fn ($s) => $s['state'] === 'live');
        $avgOf = function (string $k) use ($live) {
            $v = array_filter(array_column($live, $k), fn ($x) => $x !== null);

            return $v ? round(array_sum($v) / count($v), 1) : null;
        };

        $totals = [
            'servers'        => count($servers),
            'servers_live'   => count($live),
            'servers_stale'  => count(array_filter($servers, fn ($s) => $s['state'] === 'stale')),
            'servers_offline'=> count(array_filter($servers, fn ($s) => $s['state'] === 'offline')),
            'apps'           => count($apps),
            'apps_ok'        => count(array_filter($apps, fn ($a) => $a['level'] === 'ok')),
            'apps_warn'      => count(array_filter($apps, fn ($a) => $a['level'] === 'warn')),
            'apps_down'      => count(array_filter($apps, fn ($a) => $a['level'] === 'crit')),
            'apps_pending'   => count(array_filter($apps, fn ($a) => $a['level'] === 'idle')),
            'cpu_avg'        => $avgOf('cpu'),
            'mem_avg'        => $avgOf('mem'),
            'crit'           => count(array_filter($alerts, fn ($a) => $a['level'] === 'crit')),
            'warn'           => count(array_filter($alerts, fn ($a) => $a['level'] === 'warn')),
            'records'        => array_sum(array_column($servers, 'records')),
            'rejected'       => array_sum(array_column($servers, 'rejected')),
        ];

        return $base + [
            'empty'   => $agents->isEmpty(),
            'totals'  => $totals,
            'servers' => $servers,
            'apps'    => $apps,
            'alerts'  => array_slice($alerts, 0, 40),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Server metrics                                                     */
    /* ------------------------------------------------------------------ */

    private function serverSection(object $stream, CarbonImmutable $start, CarbonImmutable $end, int $bucket): array
    {
        $latestRow = DB::table('nadi_telemetry_records')
            ->where('stream_id', $stream->id)
            ->orderByDesc('observed_at')
            ->first(['observed_at', 'data']);

        $latest = $latestRow ? (json_decode($latestRow->data, true)['data'] ?? []) : [];

        // Indeks disk root dan interface jaringan utama diambil dari record terbaru.
        $disks = $this->filterDisks($latest['disks'] ?? []);

        $rootIdx = 0;
        foreach (($latest['disks'] ?? []) as $i => $d) {
            if (($d['mount_point'] ?? null) === '/') {
                $rootIdx = (int) $i;
                break;
            }
        }

        $netIdx  = 0;
        $netBest = -1;
        foreach (($latest['network_interfaces'] ?? []) as $i => $n) {
            if (($n['interface'] ?? '') === 'lo') {
                continue;
            }
            $traffic = (float) ($n['receive_bytes'] ?? 0) + (float) ($n['transmit_bytes'] ?? 0);
            if ($traffic > $netBest) {
                $netBest = $traffic;
                $netIdx  = (int) $i;
            }
        }

        $b = $this->bucketExpr($bucket);
        $p = '$.data';

        $rows = DB::select("
            SELECT {$b} AS bk,
                   COUNT(*) AS n,
                   {$this->num('r', "$p.cpu.usage_percent", 'AVG')}            AS cpu_avg,
                   {$this->num('r', "$p.cpu.usage_percent", 'MAX')}            AS cpu_max,
                   {$this->num('r', "$p.memory.usage_percent", 'AVG')}         AS mem_avg,
                   {$this->num('r', "$p.memory.usage_percent", 'MAX')}         AS mem_max,
                   {$this->num('r', "$p.load_average.one_minute", 'AVG')}     AS l1,
                   {$this->num('r', "$p.load_average.five_minutes", 'AVG')}    AS l5,
                   {$this->num('r', "$p.load_average.fifteen_minutes", 'AVG')} AS l15,
                   {$this->num('r', "$p.disks[{$rootIdx}].usage_percent", 'MAX')} AS disk_pct,
                   {$this->num('r', "$p.disks[{$rootIdx}].used_bytes", 'MAX', 0)} AS disk_used,
                   {$this->num('r', "$p.network_interfaces[{$netIdx}].receive_bytes", 'MAX', 0)}    AS rx,
                   {$this->num('r', "$p.network_interfaces[{$netIdx}].transmit_bytes", 'MAX', 0)}   AS tx,
                   {$this->num('r', "$p.network_interfaces[{$netIdx}].receive_dropped", 'MAX', 0)}  AS rx_drop,
                   {$this->num('r', "$p.network_interfaces[{$netIdx}].receive_packets", 'MAX', 0)}  AS rx_pkt,
                   {$this->num('r', '$.data.uptime_seconds', 'MAX', 0)}        AS uptime
            FROM nadi_telemetry_records r
            WHERE r.stream_id = ? AND r.observed_at BETWEEN ? AND ?
            GROUP BY bk
            ORDER BY bk
        ", [$stream->id, $this->sql($start), $this->sql($end)]);

        $s = [
            't' => [], 'samples' => [],
            'cpu_avg' => [], 'cpu_max' => [], 'mem_avg' => [], 'mem_max' => [],
            'load1' => [], 'load5' => [], 'load15' => [],
            'disk_pct' => [], 'rx_rate' => [], 'tx_rate' => [],
        ];

        $prev = null;
        $diskUsed = [];
        $uptimeDrops = [];

        foreach ($rows as $r) {
            $ts = (int) $r->bk * $bucket;

            $s['t'][]       = $this->iso(CarbonImmutable::createFromTimestampUTC($ts));
            $s['samples'][] = (int) $r->n;
            $s['cpu_avg'][] = $this->f($r->cpu_avg, 2);
            $s['cpu_max'][] = $this->f($r->cpu_max, 2);
            $s['mem_avg'][] = $this->f($r->mem_avg, 2);
            $s['mem_max'][] = $this->f($r->mem_max, 2);
            $s['load1'][]   = $this->f($r->l1, 3);
            $s['load5'][]   = $this->f($r->l5, 3);
            $s['load15'][]  = $this->f($r->l15, 3);
            $s['disk_pct'][] = $this->f($r->disk_pct, 2);

            // Counter jaringan kumulatif => laju (byte/detik) dari selisih antar bucket.
            $rxRate = $txRate = null;
            if ($prev && $ts > $prev['ts']) {
                $dt = $ts - $prev['ts'];
                if ($r->rx !== null && $prev['rx'] !== null && $r->rx >= $prev['rx']) {
                    $rxRate = round(($r->rx - $prev['rx']) / $dt, 1);
                }
                if ($r->tx !== null && $prev['tx'] !== null && $r->tx >= $prev['tx']) {
                    $txRate = round(($r->tx - $prev['tx']) / $dt, 1);
                }
                if ($r->uptime !== null && $prev['uptime'] !== null && $r->uptime < $prev['uptime']) {
                    $uptimeDrops[] = $this->iso(CarbonImmutable::createFromTimestampUTC($ts));
                }
            }
            $s['rx_rate'][] = $rxRate;
            $s['tx_rate'][] = $txRate;

            if ($r->disk_used !== null) {
                $diskUsed[] = [$ts, (float) $r->disk_used];
            }

            $prev = ['ts' => $ts, 'rx' => $r->rx, 'tx' => $r->tx, 'uptime' => $r->uptime];
        }

        $firstRow = $rows[0] ?? null;
        $lastRow  = $rows ? $rows[count($rows) - 1] : null;

        $cpuNow = $latest['cpu']['usage_percent'] ?? $this->lastNonNull($s['cpu_avg']);

        $net = null;
        if (isset($latest['network_interfaces'][$netIdx])) {
            $n = $latest['network_interfaces'][$netIdx];
            $pkts = (float) ($n['receive_packets'] ?? 0);
            $net = $n + [
                'drop_ratio' => $pkts > 0 ? round(((float) ($n['receive_dropped'] ?? 0)) / $pkts * 100, 2) : 0,
                'dropped_in_window' => ($firstRow && $lastRow && $firstRow->rx_drop !== null && $lastRow->rx_drop !== null)
                    ? max(0, (int) $lastRow->rx_drop - (int) $firstRow->rx_drop) : null,
            ];
        }

        $cores = $latest['cpu']['logical_cores'] ?? null;
        $mem   = $latest['memory'] ?? null;

        return [
            'stream_id'   => $stream->id,
            'observed_at' => $latestRow ? $this->iso($latestRow->observed_at) : null,
            'hostname'    => $latest['hostname'] ?? null,
            'os'          => $latest['os'] ?? null,
            'architecture'=> $latest['architecture'] ?? null,
            'uptime'      => isset($latest['uptime_seconds']) ? (float) $latest['uptime_seconds'] : null,
            'cpu'         => ['usage' => $cpuNow !== null ? round((float) $cpuNow, 2) : null, 'cores' => $cores],
            'memory'      => $mem,
            'load'        => isset($latest['load_average']) ? $latest['load_average'] + [
                'per_core' => ($cores && isset($latest['load_average']['one_minute']))
                    ? round($latest['load_average']['one_minute'] / $cores, 3) : null,
            ] : null,
            'disks'       => $disks,
            'network'     => $net,
            'nginx'       => $this->nginxSummary($latest['nginx'] ?? null),
            'stats'       => [
                'cpu_avg'  => $this->avg($s['cpu_avg']),
                'cpu_peak' => $this->maxOf($s['cpu_max']),
                'mem_avg'  => $this->avg($s['mem_avg']),
                'mem_peak' => $this->maxOf($s['mem_max']),
                'load_peak'=> $this->maxOf($s['load1']),
                'rx_avg'   => $this->avg($s['rx_rate']),
                'tx_avg'   => $this->avg($s['tx_rate']),
                'rx_peak'  => $this->maxOf($s['rx_rate']),
                'tx_peak'  => $this->maxOf($s['tx_rate']),
                'samples'  => array_sum($s['samples']),
            ],
            'disk_trend'  => $this->diskTrend($diskUsed, $disks[0] ?? null),
            'reboots'     => $uptimeDrops,
            'series'      => $s,
        ];
    }

    private function filterDisks(array $disks): array
    {
        $seen = [];
        $out  = [];

        foreach ($disks as $d) {
            $fs    = strtolower((string) ($d['filesystem'] ?? ''));
            $mount = (string) ($d['mount_point'] ?? '');

            if (in_array($fs, self::PSEUDO_FS, true) || str_starts_with($mount, '/snap/')) {
                continue;
            }

            // Bind mount (mis. /var/tmp) memakai volume yang sama dengan "/" => digabung.
            $sig = $fs . '|' . ($d['total_bytes'] ?? 0) . '|' . ($d['used_bytes'] ?? 0);

            if (isset($seen[$sig])) {
                $out[$seen[$sig]]['aliases'][] = $mount;
                continue;
            }

            $seen[$sig] = count($out);
            $out[] = [
                'mount'      => $mount,
                'filesystem' => $d['filesystem'] ?? null,
                'total'      => (float) ($d['total_bytes'] ?? 0),
                'used'       => (float) ($d['used_bytes'] ?? 0),
                'available'  => (float) ($d['available_bytes'] ?? 0),
                'usage'      => round((float) ($d['usage_percent'] ?? 0), 2),
                'aliases'    => [],
            ];
        }

        // Root volume selalu di atas.
        usort($out, fn ($a, $b) => ($b['mount'] === '/') <=> ($a['mount'] === '/'));

        return $out;
    }

    /** Regresi linear sederhana: laju pertumbuhan disk root dan estimasi penuh. */
    private function diskTrend(array $points, ?array $root): ?array
    {
        $n = count($points);

        if ($n < 3 || ! $root) {
            return null;
        }

        $span = $points[$n - 1][0] - $points[0][0];

        if ($span < 1800) {
            return null;
        }

        $x0 = $points[0][0];
        $sx = $sy = $sxx = $sxy = 0.0;

        foreach ($points as [$t, $y]) {
            $x = $t - $x0;
            $sx += $x; $sy += $y; $sxx += $x * $x; $sxy += $x * $y;
        }

        $den = $n * $sxx - $sx * $sx;

        if ($den == 0.0) {
            return null;
        }

        $slope  = ($n * $sxy - $sx * $sy) / $den; // byte / detik
        $perDay = $slope * 86400;
        $etaDays = ($slope > 0 && $root['available'] > 0) ? $root['available'] / $perDay : null;

        return [
            'bytes_per_day' => round($perDay),
            'eta_days'      => $etaDays !== null ? round($etaDays, 1) : null,
            'direction'     => $perDay > 1024 * 1024 ? 'growing' : ($perDay < -1024 * 1024 ? 'shrinking' : 'stable'),
        ];
    }

    private function nginxSummary(?array $n): ?array
    {
        if (! $n) {
            return null;
        }

        return [
            'service'     => $n['service']['status'] ?? null,
            'active'      => $n['service']['active_state'] ?? null,
            'collector'   => $n['collector']['status'] ?? null,
            'error'       => $n['collector']['error'] ?? null,
            'processes'   => $n['total_processes'] ?? null,
            'workers'     => $n['worker_processes'] ?? null,
            'metrics_ep'  => $n['metrics_endpoint']['status'] ?? null,
            'metrics_why' => $n['metrics_endpoint']['reason'] ?? null,
            'http_probe'  => $n['http_availability']['status'] ?? null,
            'http_why'    => $n['http_availability']['reason'] ?? null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Application metrics (probe bridge)                                 */
    /* ------------------------------------------------------------------ */

    private function applicationSection(object $stream, CarbonImmutable $start, CarbonImmutable $end, int $bucket): array
    {
        $latestRow = DB::table('nadi_telemetry_records')
            ->where('stream_id', $stream->id)
            ->orderByDesc('observed_at')
            ->first(['observed_at', 'data']);

        $latest = $latestRow ? (json_decode($latestRow->data, true)['data'] ?? []) : [];

        $okList = "'" . implode("','", self::PROBE_OK) . "'";
        $b      = $this->bucketExpr($bucket);
        $cols   = '';

        foreach (self::PROBES as $probe) {
            $cols .= ",
                {$this->num('r', "$.data.{$probe}.latency_ms", 'AVG', 3)} AS {$probe}_lat,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(r.data, '$.data.{$probe}.status')) IN ({$okList}) THEN 1 ELSE 0 END) AS {$probe}_ok";
        }

        $rows = DB::select("
            SELECT {$b} AS bk, COUNT(*) AS n {$cols}
            FROM nadi_telemetry_records r
            WHERE r.stream_id = ? AND r.observed_at BETWEEN ? AND ?
            GROUP BY bk
            ORDER BY bk
        ", [$stream->id, $this->sql($start), $this->sql($end)]);

        $series = ['t' => []];
        $okSum  = array_fill_keys(self::PROBES, 0);
        $total  = 0;

        foreach (self::PROBES as $p) {
            $series[$p] = [];
        }

        foreach ($rows as $r) {
            $series['t'][] = $this->iso(CarbonImmutable::createFromTimestampUTC((int) $r->bk * $bucket));
            $total        += (int) $r->n;

            foreach (self::PROBES as $p) {
                $series[$p][] = $this->f($r->{$p . '_lat'}, 2);
                $series[$p . '_up'][] = (int) $r->n > 0
                    ? (int) $r->{$p . '_ok'} / (int) $r->n
                    : null;
                $okSum[$p]   += (int) $r->{$p . '_ok'};
            }
        }

        $probes = [];

        foreach (self::PROBES as $p) {
            $row = $latest[$p] ?? null;

            $probes[$p] = [
                'status'       => $row['status'] ?? 'unknown',
                'error_code'   => $row['error_code'] ?? null,
                'http_status'  => $row['http_status'] ?? null,
                'latency_ms'   => isset($row['latency_ms']) ? (float) $row['latency_ms'] : null,
                'availability' => $total > 0 ? round($okSum[$p] / $total * 100, 1) : null,
                'latency_avg'  => $this->avg($series[$p]),
                'latency_max'  => $this->maxOf($series[$p]),
            ];
        }

        return [
            'stream_id'          => $stream->id,
            'application_id'     => $stream->application_id,
            'name'               => $stream->application_name,
            'key'                => $stream->application_key,
            'environment'        => $stream->environment,
            'framework'          => $stream->framework,
            'monitoring_status'  => $stream->monitoring_status,
            'activation_status'  => $stream->activation_status,
            'observed_at'        => $latestRow ? $this->iso($latestRow->observed_at) : null,
            'samples'            => $total,
            'probes'             => $probes,
            'series'             => $series,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Pipeline / ingest                                                  */
    /* ------------------------------------------------------------------ */

    private function pipelineSection(string $agentId, $streams, CarbonImmutable $start, CarbonImmutable $end, int $bucket, CarbonImmutable $now): array
    {
        $b = $this->bucketExpr($bucket, 'b.received_at');

        $rows = DB::select("
            SELECT {$b} AS bk,
                   COUNT(*)                   AS batches,
                   SUM(b.record_count)        AS records,
                   SUM(b.accepted_count)      AS accepted,
                   SUM(b.duplicate_count)     AS duplicates,
                   SUM(b.rejected_count)      AS rejected,
                   AVG(TIMESTAMPDIFF(MICROSECOND, b.sent_at, b.received_at)) / 1000 AS lag_ms
            FROM nadi_telemetry_batches b
            WHERE b.agent_id = ? AND b.received_at BETWEEN ? AND ?
            GROUP BY bk
            ORDER BY bk
        ", [$agentId, $this->sql($start), $this->sql($end)]);

        $series = ['t' => [], 'accepted' => [], 'duplicates' => [], 'rejected' => [], 'lag_ms' => []];
        $tot    = ['batches' => 0, 'records' => 0, 'accepted' => 0, 'duplicates' => 0, 'rejected' => 0];
        $lagSum = 0.0;
        $lagN   = 0;
        $lagMax = null;

        foreach ($rows as $r) {
            $series['t'][]          = $this->iso(CarbonImmutable::createFromTimestampUTC((int) $r->bk * $bucket));
            $series['accepted'][]   = (int) $r->accepted;
            $series['duplicates'][] = (int) $r->duplicates;
            $series['rejected'][]   = (int) $r->rejected;
            $series['lag_ms'][]     = $this->f($r->lag_ms, 1);

            foreach (['batches', 'records', 'accepted', 'duplicates', 'rejected'] as $k) {
                $tot[$k] += (int) $r->{$k};
            }

            if ($r->lag_ms !== null) {
                $lagSum += (float) $r->lag_ms * (int) $r->batches;
                $lagN   += (int) $r->batches;
                $lagMax  = max($lagMax ?? 0, (float) $r->lag_ms);
            }
        }

        $perStream = DB::table('nadi_telemetry_records')
            ->where('agent_id', $agentId)
            ->whereBetween('observed_at', [$this->sql($start), $this->sql($end)])
            ->groupBy('stream_id')
            ->selectRaw('stream_id, COUNT(*) AS n, MIN(observed_at) AS first_at, MAX(observed_at) AS last_at')
            ->get()->keyBy('stream_id');

        $streamRows = $streams->map(function ($s) use ($perStream, $now) {
            $last = $s->last_received_at ? CarbonImmutable::parse($s->last_received_at, 'UTC') : null;
            $age  = $last ? max(0, $now->getTimestamp() - $last->getTimestamp()) : null;
            $inWin = $perStream[$s->id] ?? null;

            $interval = null;
            if ($inWin && $inWin->n > 1) {
                $span = CarbonImmutable::parse($inWin->last_at, 'UTC')->getTimestamp() - CarbonImmutable::parse($inWin->first_at, 'UTC')->getTimestamp();
                $interval = $span > 0 ? round($span / ($inWin->n - 1), 1) : null;
            }

            $missing = max(0, (int) $s->max_sequence_seen - (int) $s->last_contiguous_sequence);

            return [
                'id'               => $s->id,
                'name'             => $s->stream_name,
                'source'           => $s->source,
                'application'      => $s->application_name,
                'last_contiguous'  => (int) $s->last_contiguous_sequence,
                'max_seen'         => (int) $s->max_sequence_seen,
                'missing'          => $missing,
                'last_observed_at' => $this->iso($s->last_observed_at),
                'last_received_at' => $this->iso($s->last_received_at),
                'age_seconds'      => $age !== null ? (int) $age : null,
                'state'            => $age === null ? 'offline' : ($age > self::OFFLINE_AFTER ? 'offline' : ($age > self::STALE_AFTER ? 'stale' : 'live')),
                'records_in_window'=> $inWin ? (int) $inWin->n : 0,
                'interval_seconds' => $interval,
            ];
        })->values()->all();

        $gaps = DB::table('nadi_telemetry_gaps')
            ->where('agent_id', $agentId)
            ->orderByDesc('gap_detected_at')
            ->limit(8)
            ->get(['id', 'stream_id', 'missing_sequence_from', 'missing_sequence_to', 'gap_started_at', 'gap_resolved_at', 'gap_status', 'description'])
            ->map(fn ($g) => [
                'id'          => $g->id,
                'from'        => $g->missing_sequence_from,
                'to'          => $g->missing_sequence_to,
                'started_at'  => $this->iso($g->gap_started_at),
                'resolved_at' => $this->iso($g->gap_resolved_at),
                'status'      => $g->gap_status,
                'description' => $g->description,
            ])->all();

        $openGaps = DB::table('nadi_telemetry_gaps')
            ->where('agent_id', $agentId)->where('gap_status', 'open')->count();

        $ackTotal = $tot['accepted'] + $tot['duplicates'] + $tot['rejected'];

        return [
            'totals'      => $tot + [
                'accept_rate' => $ackTotal > 0 ? round($tot['accepted'] / $ackTotal * 100, 2) : null,
                'lag_avg_ms'  => $lagN > 0 ? round($lagSum / $lagN, 1) : null,
                'lag_max_ms'  => $lagMax !== null ? round($lagMax, 1) : null,
            ],
            'streams'     => $streamRows,
            'gaps'        => $gaps,
            'open_gaps'   => $openGaps,
            'series'      => $series,
        ];
    }

    private function collectorSection(string $agentId): array
    {
        return DB::table('nadi_telemetry_collector_states as c')
            ->leftJoin('nadi_applications as a', 'a.id', '=', 'c.application_id')
            ->where('c.agent_id', $agentId)
            ->orderBy('c.collector_name')
            ->get([
                'c.collector_name', 'c.collector_status', 'c.last_attempt_at', 'c.last_success_at',
                'c.last_error_at', 'c.last_error_code', 'c.last_error_message', 'a.application_name',
            ])
            ->map(fn ($c) => [
                'name'        => $c->collector_name,
                'status'      => $c->collector_status,
                'application' => $c->application_name,
                'last_success_at' => $this->iso($c->last_success_at),
                'last_error_at'   => $this->iso($c->last_error_at),
                'error_code'      => $c->last_error_code,
                'error_message'   => $c->last_error_message,
            ])->all();
    }

    private function recentRecords(string $agentId): array
    {
        return DB::table('nadi_telemetry_records as r')
            ->join('nadi_telemetry_streams as s', 's.id', '=', 'r.stream_id')
            ->where('r.agent_id', $agentId)
            ->orderByDesc('r.observed_at')
            ->limit(14)
            ->get(['r.id', 'r.sequence', 'r.observed_at', 'r.collected_at', 'r.created_at', 's.stream_name', DB::raw('LENGTH(r.data) AS size')])
            ->map(fn ($r) => [
                'id'          => $r->id,
                'stream'      => $r->stream_name,
                'sequence'    => (int) $r->sequence,
                'observed_at' => $this->iso($r->observed_at),
                'received_at' => $this->iso($r->created_at),
                'size'        => (int) $r->size,
            ])->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Analisa otomatis                                                   */
    /* ------------------------------------------------------------------ */

    private function insights(array $p): array
    {
        $out = [];
        $add = function (string $level, string $title, string $detail) use (&$out) {
            $out[] = ['level' => $level, 'title' => $title, 'detail' => $detail];
        };

        $srv = $p['server'];

        // Kesegaran data
        $worst = null;
        foreach ($p['pipeline']['streams'] as $s) {
            if ($worst === null || ($s['age_seconds'] ?? PHP_INT_MAX) > ($worst['age_seconds'] ?? PHP_INT_MAX)) {
                $worst = $s;
            }
        }
        if ($worst) {
            if ($worst['state'] === 'offline') {
                $add('crit', 'Telemetry stream offline', sprintf(
                    'Stream "%s" terakhir menerima data %s lalu. Agent kemungkinan berhenti atau tidak punya koneksi.',
                    $worst['name'], $this->human($worst['age_seconds'])
                ));
            } elseif ($worst['state'] === 'stale') {
                $add('warn', 'Telemetry mulai terlambat', sprintf('Stream "%s" belum mengirim data selama %s.', $worst['name'], $this->human($worst['age_seconds'])));
            } else {
                $add('ok', 'Telemetry mengalir normal', 'Semua stream menerima data dalam ' . self::STALE_AFTER . ' detik terakhir.');
            }
        }

        if ($srv) {
            $st = $srv['stats'];

            if (($srv['cpu']['usage'] ?? null) !== null) {
                if ($st['cpu_peak'] !== null && $st['cpu_peak'] >= 85) {
                    $add('warn', 'Lonjakan CPU', sprintf('CPU mencapai puncak %.1f%% (rata-rata %.1f%%) pada window ini.', $st['cpu_peak'], $st['cpu_avg']));
                } else {
                    $add('ok', 'CPU sehat', sprintf('Rata-rata %.1f%%, puncak %.1f%% dari %d core. Headroom besar.', $st['cpu_avg'] ?? 0, $st['cpu_peak'] ?? 0, $srv['cpu']['cores'] ?? 0));
                }
            }

            $mu = $srv['memory']['usage_percent'] ?? null;
            if ($mu !== null) {
                if ($mu >= 90) {
                    $add('crit', 'Memori hampir habis', sprintf('Penggunaan memori %.1f%%.', $mu));
                } elseif ($mu >= 80) {
                    $add('warn', 'Memori tinggi', sprintf('Penggunaan memori %.1f%% (puncak %.1f%%).', $mu, $st['mem_peak'] ?? $mu));
                }
            }

            $lpc = $srv['load']['per_core'] ?? null;
            if ($lpc !== null && $lpc >= 1) {
                $add('warn', 'Load average tinggi', sprintf('Load 1 menit %.2f untuk %d core (%.2f per core).', $srv['load']['one_minute'], $srv['cpu']['cores'], $lpc));
            }

            foreach ($srv['disks'] as $d) {
                if ($d['usage'] >= 90) {
                    $add('crit', "Disk {$d['mount']} hampir penuh", sprintf('Terpakai %.1f%%, sisa %s.', $d['usage'], $this->bytes($d['available'])));
                } elseif ($d['usage'] >= 75) {
                    $add('warn', "Disk {$d['mount']} mulai penuh", sprintf('Terpakai %.1f%%, sisa %s.', $d['usage'], $this->bytes($d['available'])));
                }
            }

            $tr = $srv['disk_trend'];
            if ($tr && $tr['direction'] === 'growing' && $tr['eta_days'] !== null) {
                $lvl = $tr['eta_days'] < 14 ? 'crit' : ($tr['eta_days'] < 45 ? 'warn' : 'info');
                $add($lvl, 'Proyeksi disk penuh', sprintf(
                    'Disk root bertambah sekitar %s/hari. Pada laju ini ruang kosong habis dalam ±%s hari.',
                    $this->bytes($tr['bytes_per_day']), rtrim(rtrim(number_format($tr['eta_days'], 1), '0'), '.')
                ));
            }

            $net = $srv['network'];
            if ($net && ($net['drop_ratio'] ?? 0) >= 1) {
                $add('warn', 'Paket jaringan banyak di-drop', sprintf(
                    'Interface %s men-drop %s paket masuk (%.2f%% dari total). Periksa broadcast/multicast noise, ring buffer NIC, atau filter di host.',
                    $net['interface'], number_format($net['receive_dropped']), $net['drop_ratio']
                ));
            }
            if ($net && ((int) ($net['receive_errors'] ?? 0) > 0 || (int) ($net['transmit_errors'] ?? 0) > 0)) {
                $add('warn', 'Error pada interface jaringan', sprintf('RX error %d, TX error %d pada %s.', $net['receive_errors'], $net['transmit_errors'], $net['interface']));
            }

            if (! empty($srv['reboots'])) {
                $add('warn', 'Server restart terdeteksi', count($srv['reboots']) . ' kali uptime reset dalam window ini.');
            }

            $ng = $srv['nginx'];
            if ($ng) {
                if (($ng['service'] ?? null) === 'running' && ($ng['collector'] ?? null) === 'unavailable') {
                    $add('info', 'Collector Nginx terbatas', sprintf(
                        'Service berjalan, tetapi detail proses tidak terbaca%s. Metrics endpoint & HTTP probe belum dikonfigurasi.',
                        $ng['error'] ? ' (' . $ng['error'] . ')' : ''
                    ));
                }
            }
        }

        // Probe aplikasi
        foreach ($p['applications'] as $app) {
            $bad = [];
            $codes = [];
            foreach ($app['probes'] as $name => $pr) {
                if (! in_array($pr['status'], self::PROBE_OK, true)) {
                    $bad[] = $name;
                    $codes[$pr['error_code'] ?? $pr['status']] = true;
                }
            }
            $n = count($app['probes']);
            if (count($bad) === $n) {
                $add('warn', "Bridge aplikasi {$app['name']} tidak menghasilkan metrik", sprintf(
                    'Seluruh %d probe tidak tersedia (%s). Endpoint bridge pada aplikasi belum aktif/terpasang, jadi metrik aplikasi belum bisa dianalisa.',
                    $n, implode(', ', array_keys($codes))
                ));
            } elseif ($bad) {
                $add('warn', "Sebagian probe {$app['name']} bermasalah", implode(', ', $bad) . ' tidak tersedia.');
            } else {
                $add('ok', "Probe {$app['name']} sehat", 'Semua probe aplikasi merespon.');
            }
        }

        // Pipeline
        $t = $p['pipeline']['totals'];
        if ($t['rejected'] > 0) {
            $add('crit', 'Record ditolak server', "{$t['rejected']} record ditolak pada window ini. Periksa validasi payload/protokol agent.");
        }
        if ($t['duplicates'] > 0) {
            $add('info', 'Record duplikat', "{$t['duplicates']} record duplikat (kemungkinan retry agent).");
        }
        if ($p['pipeline']['open_gaps'] > 0) {
            $add('warn', 'Gap sequence terbuka', "{$p['pipeline']['open_gaps']} gap belum terselesaikan; sebagian data belum diterima.");
        }
        foreach ($p['pipeline']['streams'] as $s) {
            if ($s['missing'] > 0) {
                $add('warn', "Sequence loncat pada {$s['name']}", "{$s['missing']} sequence belum berurutan (contiguous {$s['last_contiguous']} / max {$s['max_seen']}).");
            }
        }
        if (($t['lag_avg_ms'] ?? 0) > 5000) {
            $add('warn', 'Lag ingest tinggi', sprintf('Rata-rata jeda kirim→terima %.0f ms.', $t['lag_avg_ms']));
        } elseif ($t['records'] > 0 && $t['rejected'] === 0 && $p['pipeline']['open_gaps'] === 0) {
            $add('ok', 'Pipeline ingest bersih', sprintf(
                '%s record diterima tanpa penolakan atau gap; jeda rata-rata %s ms.',
                number_format($t['accepted']), $t['lag_avg_ms'] !== null ? number_format($t['lag_avg_ms'], 0) : '-'
            ));
        }

        // Urutkan berdasarkan keparahan
        $rank = ['crit' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3];
        usort($out, fn ($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Helper                                                             */
    /* ------------------------------------------------------------------ */

    /** Ekspresi bucket yang independen dari timezone sesi MySQL. */
    private function bucketExpr(int $bucket, string $col = 'r.observed_at'): string
    {
        return "FLOOR(TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', {$col}) / " . (int) $bucket . ')';
    }

    /**
     * Agregasi numerik dari path JSON. $path & $fn hanya berasal dari konstanta di controller ini
     * (bukan input user), nilai JSON null dinormalkan menjadi SQL NULL.
     */
    private function num(string $alias, string $path, string $fn = 'AVG', int $scale = 4): string
    {
        return "{$fn}(CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.data, '{$path}')), 'null') AS DECIMAL(28,{$scale})))";
    }

    private function sql(CarbonImmutable $t): string
    {
        return $t->setTimezone('UTC')->format('Y-m-d H:i:s.u');
    }

    private function iso($v): ?string
    {
        if (! $v) {
            return null;
        }

        $c = $v instanceof CarbonImmutable ? $v : CarbonImmutable::parse($v, 'UTC');

        return $c->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    private function f($v, int $dec = 2): ?float
    {
        return $v === null ? null : round((float) $v, $dec);
    }

    private function avg(array $a): ?float
    {
        $a = array_filter($a, fn ($v) => $v !== null);

        return $a ? round(array_sum($a) / count($a), 2) : null;
    }

    private function maxOf(array $a): ?float
    {
        $a = array_filter($a, fn ($v) => $v !== null);

        return $a ? (float) max($a) : null;
    }

    private function lastNonNull(array $a)
    {
        for ($i = count($a) - 1; $i >= 0; $i--) {
            if ($a[$i] !== null) {
                return $a[$i];
            }
        }

        return null;
    }

    private function human(?int $s): string
    {
        if ($s === null) {
            return 'tidak diketahui';
        }
        if ($s < 90) {
            return $s . ' detik';
        }
        if ($s < 5400) {
            return round($s / 60) . ' menit';
        }
        if ($s < 172800) {
            return round($s / 3600, 1) . ' jam';
        }

        return round($s / 86400, 1) . ' hari';
    }

    private function bytes(float $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $b = abs($b);

        while ($b >= 1024 && $i < count($u) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, $i > 1 ? 1 : 0) . ' ' . $u[$i];
    }
}