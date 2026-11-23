<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $servers = DB::table('nadi_servers')
            ->leftJoin('nadi_agents', 'nadi_agents.server_id', '=', 'nadi_servers.id')
            ->leftJoinSub(
                DB::table('nadi_telemetry_streams')
                    ->select('agent_id')
                    ->selectRaw('MAX(last_received_at) AS telemetry_received_at')
                    ->groupBy('agent_id'),
                'telemetry_freshness',
                'telemetry_freshness.agent_id',
                '=',
                'nadi_agents.id'
            )
            ->select(
                'nadi_servers.id',
                'nadi_servers.hostname',
                'nadi_servers.ip_address',
                'nadi_servers.os_name',
                'nadi_servers.os_version',
                'nadi_servers.architecture',
                'nadi_servers.status AS server_status',
                'nadi_agents.id AS agent_id',
                'nadi_agents.agent_version',
                'nadi_agents.protocol_version',
                'nadi_agents.installation_state',
                'nadi_agents.status AS agent_status',
                'nadi_agents.last_control_at',
                'telemetry_freshness.telemetry_received_at'
            )
            ->orderBy('nadi_servers.hostname')
            ->get();

        $applications = DB::table('nadi_applications')
            ->join('nadi_agents', 'nadi_agents.id', '=', 'nadi_applications.agent_id')
            ->join('nadi_servers', 'nadi_servers.id', '=', 'nadi_agents.server_id')
            ->leftJoinSub(
                DB::table('nadi_telemetry_streams')
                    ->select('application_id')
                    ->selectRaw('MAX(last_received_at) AS telemetry_received_at')
                    ->whereNotNull('application_id')
                    ->groupBy('application_id'),
                'application_freshness',
                'application_freshness.application_id',
                '=',
                'nadi_applications.id'
            )
            ->select(
                'nadi_applications.id',
                'nadi_applications.application_name',
                'nadi_applications.application_key',
                'nadi_applications.environment',
                'nadi_applications.framework',
                'nadi_applications.framework_version',
                'nadi_applications.php_version',
                'nadi_applications.registration_status',
                'nadi_applications.activation_status',
                'nadi_applications.monitoring_status',
                'nadi_agents.id AS agent_id',
                'nadi_servers.hostname',
                'application_freshness.telemetry_received_at'
            )
            ->whereNull('nadi_applications.unregistered_at')
            ->orderBy('nadi_applications.application_name')
            ->get();

        $latestRecords = DB::table('nadi_telemetry_records')
            ->join('nadi_telemetry_streams', 'nadi_telemetry_streams.id', '=', 'nadi_telemetry_records.stream_id')
            ->select(
                'nadi_telemetry_records.application_id',
                'nadi_telemetry_records.observed_at',
                'nadi_telemetry_records.data',
                'nadi_telemetry_streams.stream_name'
            )
            ->whereIn('nadi_telemetry_streams.stream_name', [
                'server_metrics',
                'application_metrics',
            ])
            ->orderByDesc('nadi_telemetry_records.observed_at')
            ->limit(100)
            ->get();

        $latestServerMetrics = null;
        $latestApplicationMetrics = [];

        foreach ($latestRecords as $record)
        {
            $payload = json_decode($record->data, true);

            if (!is_array($payload))
            {
                continue;
            }

            $metricData = $payload['data'] ?? $payload;

            if ($record->stream_name === 'server_metrics' && $record->application_id === null)
            {
                if ($latestServerMetrics === null)
                {
                    $latestServerMetrics = [
                        'observed_at' => $record->observed_at,
                        'data' => $metricData,
                    ];
                }

                continue;
            }

            if (
                $record->stream_name === 'application_metrics'
                && $record->application_id !== null
                && !array_key_exists($record->application_id, $latestApplicationMetrics)
            )
            {
                $latestApplicationMetrics[$record->application_id] = [
                    'observed_at' => $record->observed_at,
                    'data' => $metricData,
                ];
            }
        }

        $latestBatches = DB::table('nadi_telemetry_batches')
            ->selectRaw('COUNT(*) AS total_batches')
            ->selectRaw("SUM(CASE WHEN batch_status = 'accepted' THEN 1 ELSE 0 END) AS accepted_batches")
            ->selectRaw('COALESCE(SUM(accepted_count), 0) AS accepted_records')
            ->selectRaw('COALESCE(SUM(duplicate_count), 0) AS duplicate_records')
            ->selectRaw('COALESCE(SUM(rejected_count), 0) AS rejected_records')
            ->first();

        $now = now();

        $servers = $servers->map(function ($server) use ($now)
        {
            $server->telemetry_age_seconds = $server->telemetry_received_at
                ? Carbon::parse($server->telemetry_received_at)->diffInSeconds($now)
                : null;

            $server->telemetry_state = $this->freshnessState(
                $server->telemetry_received_at,
                $now
            );

            return $server;
        });

        $applications = $applications->map(function ($application) use ($now, $latestApplicationMetrics)
        {
            $application->telemetry_age_seconds = $application->telemetry_received_at
                ? Carbon::parse($application->telemetry_received_at)->diffInSeconds($now)
                : null;

            $application->telemetry_state = $this->freshnessState(
                $application->telemetry_received_at,
                $now
            );

            $application->bridge = $latestApplicationMetrics[$application->id]['data'] ?? null;
            $application->bridge_observed_at = $latestApplicationMetrics[$application->id]['observed_at'] ?? null;

            return $application;
        });

        $serverData = $latestServerMetrics['data'] ?? [];
        $cpuUsage = data_get($serverData, 'cpu.usage_percent');
        $memoryUsage = data_get($serverData, 'memory.usage_percent');

        $httpEventCount = DB::table('nadi_http_request_events')->count();

        return view('dashboard', [
            'servers' => $servers,
            'applications' => $applications,
            'serverCount' => $servers->count(),
            'agentCount' => $servers->whereNotNull('agent_id')->count(),
            'applicationCount' => $applications->count(),
            'approvedApplicationCount' => $applications->where('registration_status', 'approved')->count(),
            'activeApplicationCount' => $applications->where('activation_status', 'active')->count(),
            'freshServerCount' => $servers->where('telemetry_state', 'fresh')->count(),
            'staleServerCount' => $servers->where('telemetry_state', 'stale')->count(),
            'cpuUsage' => is_numeric($cpuUsage) ? (float) $cpuUsage : null,
            'memoryUsage' => is_numeric($memoryUsage) ? (float) $memoryUsage : null,
            'latestServerObservedAt' => $latestServerMetrics['observed_at'] ?? null,
            'batchStats' => $latestBatches,
            'httpEventCount' => $httpEventCount,
        ]);
    }

    private function freshnessState($timestamp, Carbon $now): string
    {
        if (!$timestamp)
        {
            return 'no-data';
        }

        $age = Carbon::parse($timestamp)->diffInSeconds($now);

        return $age <= 90 ? 'fresh' : 'stale';
    }
}
