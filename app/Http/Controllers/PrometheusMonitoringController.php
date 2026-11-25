<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrometheusMonitoringController extends Controller
{
    public function index(): View
    {
        $servers = DB::table('nadi_servers as servers')
            ->join('nadi_agents as agents', 'agents.server_id', '=', 'servers.id')
            ->where('agents.installation_state', 'installed')
            ->orderBy('servers.hostname')
            ->get([
                'servers.id',
                'servers.hostname',
                'servers.ip_address',
            ])
            ->unique('id')
            ->values();

        $applications = DB::table('nadi_applications as applications')
            ->join('nadi_agents as agents', 'agents.id', '=', 'applications.agent_id')
            ->join('nadi_servers as servers', 'servers.id', '=', 'agents.server_id')
            ->where('agents.installation_state', 'installed')
            ->whereNull('applications.unregistered_at')
            ->orderBy('applications.application_name')
            ->get([
                'applications.id',
                'applications.application_name',
                'applications.environment',
                'servers.hostname',
            ]);

        return view('pages.monitoring.infrastructure', [
            'servers' => $servers,
            'applications' => $applications,
        ]);
    }
}
