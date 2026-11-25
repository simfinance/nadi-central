<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlertManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:all,firing,resolved',
            ],
            'severity' => [
                'nullable',
                'string',
                'max:100',
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $status = $validated['status'] ?? 'all';
        $severity = $validated['severity'] ?? '';
        $search = trim($validated['search'] ?? '');

        $query = DB::table('nadi_alerts');

        if ($status !== 'all')
        {
            $query->where('status', $status);
        }

        if ($severity !== '')
        {
            $query->where('severity', $severity);
        }

        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder
                    ->where('alertname', 'like', '%' . $search . '%')
                    ->orWhere('summary', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('fingerprint', 'like', '%' . $search . '%');
            });
        }

        $alerts = $query
            ->orderByRaw("CASE WHEN status = 'firing' THEN 0 ELSE 1 END")
            ->orderByDesc('last_received_at')
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'firing' => DB::table('nadi_alerts')
                ->where('status', 'firing')
                ->count(),
            'resolved' => DB::table('nadi_alerts')
                ->where('status', 'resolved')
                ->count(),
            'events' => DB::table('nadi_alert_events')->count(),
        ];

        $severities = DB::table('nadi_alerts')
            ->whereNotNull('severity')
            ->select('severity')
            ->distinct()
            ->orderBy('severity')
            ->pluck('severity');

        return view('management.alerts', compact(
            'alerts',
            'totals',
            'status',
            'severity',
            'search',
            'severities'
        ));
    }

    public function show(string $fingerprint): View
    {
        $alert = DB::table('nadi_alerts')
            ->where('fingerprint', $fingerprint)
            ->first();

        abort_if(!$alert, 404);

        $events = DB::table('nadi_alert_events')
            ->where('fingerprint', $fingerprint)
            ->orderByDesc('received_at')
            ->paginate(30);

        return view('management.alert-detail', compact(
            'alert',
            'events'
        ));
    }
}
