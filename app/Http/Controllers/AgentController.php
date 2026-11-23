<?php

namespace App\Http\Controllers;

use App\Models\NadiAgent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(Request $request): View
    {
        $query = NadiAgent::query()
            ->with('server')
            ->withCount('applications');

        if ($request->filled('search'))
        {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('agent_instance_id', 'like', "%{$search}%")
                    ->orWhere('agent_version', 'like', "%{$search}%")
                    ->orWhere('protocol_version', 'like', "%{$search}%")
                    ->orWhereHas('server', function ($query) use ($search) {
                        $query->where('hostname', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status'))
        {
            $query->where('status', $request->status);
        }

        if ($request->filled('installation_state'))
        {
            $query->where('installation_state', $request->installation_state);
        }

        $agents = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $totalAgents = NadiAgent::count();

        $registeredAgents = NadiAgent::where(
            'status',
            'registered'
        )->count();

        $installedAgents = NadiAgent::where(
            'installation_state',
            'installed'
        )->count();

        $uninstalledAgents = NadiAgent::where(
            'installation_state',
            'uninstalled'
        )->count();

        $statuses = NadiAgent::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $installationStates = NadiAgent::query()
            ->select('installation_state')
            ->distinct()
            ->orderBy('installation_state')
            ->pluck('installation_state');

        return view('pages.management.agents', compact(
            'agents',
            'totalAgents',
            'registeredAgents',
            'installedAgents',
            'uninstalledAgents',
            'statuses',
            'installationStates'
        ));
    }
}
