<?php

namespace App\Http\Controllers;

use App\Models\NadiServer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(Request $request): View
    {
        $query = NadiServer::query();

        if ($request->filled('search'))
        {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('hostname', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('os_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status'))
        {
            $query->where('status', $request->status);
        }

        $servers = $query
            ->orderBy('hostname')
            ->paginate(15)
            ->withQueryString();

        $totalServers = NadiServer::count();

        $registeredServers = NadiServer::where('status', 'registered')
            ->count();

        $otherStatusServers = $totalServers - $registeredServers;

        $statuses = NadiServer::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('pages.monitoring.servers', compact(
            'servers',
            'totalServers',
            'registeredServers',
            'otherStatusServers',
            'statuses'
        ));
    }
}