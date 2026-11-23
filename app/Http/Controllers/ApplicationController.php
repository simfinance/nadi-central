<?php

namespace App\Http\Controllers;

use App\Models\NadiApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = NadiApplication::query()
            ->with([
                'agent.server',
                'endpoint',
            ])
            ->withCount([
                'credentials',
                'credentials as active_credentials_count' => function ($query) {
                    $query->where('status', 'active');
                },
            ]);

        if ($request->filled('search'))
        {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('application_name', 'like', "%{$search}%")
                    ->orWhere('application_key', 'like', "%{$search}%")
                    ->orWhere('environment', 'like', "%{$search}%")
                    ->orWhere('framework', 'like', "%{$search}%")
                    ->orWhereHas('agent.server', function ($query) use ($search) {
                        $query->where('hostname', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('registration_status'))
        {
            $query->where(
                'registration_status',
                $request->registration_status
            );
        }

        if ($request->filled('activation_status'))
        {
            $query->where(
                'activation_status',
                $request->activation_status
            );
        }

        if ($request->filled('monitoring_status'))
        {
            $query->where(
                'monitoring_status',
                $request->monitoring_status
            );
        }

        if ($request->filled('environment'))
        {
            $query->where('environment', $request->environment);
        }

        $applications = $query
            ->orderBy('application_name')
            ->paginate(15)
            ->withQueryString();

        $totalApplications = NadiApplication::count();

        $pendingRegistrations = NadiApplication::where(
            'registration_status',
            'pending'
        )->count();

        $activeMonitoring = NadiApplication::where(
            'monitoring_status',
            'active'
        )->count();

        $registrationStatuses = NadiApplication::query()
            ->select('registration_status')
            ->distinct()
            ->orderBy('registration_status')
            ->pluck('registration_status');

        $activationStatuses = NadiApplication::query()
            ->select('activation_status')
            ->distinct()
            ->orderBy('activation_status')
            ->pluck('activation_status');

        $monitoringStatuses = NadiApplication::query()
            ->select('monitoring_status')
            ->distinct()
            ->orderBy('monitoring_status')
            ->pluck('monitoring_status');

        $environments = NadiApplication::query()
            ->select('environment')
            ->distinct()
            ->orderBy('environment')
            ->pluck('environment');

        return view('pages.monitoring.applications', compact(
            'applications',
            'totalApplications',
            'pendingRegistrations',
            'activeMonitoring',
            'registrationStatuses',
            'activationStatuses',
            'monitoringStatuses',
            'environments'
        ));
    }
}