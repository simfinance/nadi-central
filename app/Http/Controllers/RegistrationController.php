<?php

namespace App\Http\Controllers;

use App\Models\NadiRegistration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $query = NadiRegistration::query()
            ->with([
                'application.agent.server',
                'agent.server',
                'reviewer',
            ]);

        if ($request->filled('search'))
        {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('application', function ($query) use ($search) {
                        $query->where('application_name', 'like', "%{$search}%")
                            ->orWhere('application_key', 'like', "%{$search}%");
                    })
                    ->orWhereHas('agent.server', function ($query) use ($search) {
                        $query->where('hostname', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status'))
        {
            $query->where('registration_status', $request->status);
        }

        $registrations = $query
            ->orderByDesc('requested_at')
            ->paginate(15)
            ->withQueryString();

        $totalRegistrations = NadiRegistration::count();

        $pendingRegistrations = NadiRegistration::where(
            'registration_status',
            'pending'
        )->count();

        $approvedRegistrations = NadiRegistration::where(
            'registration_status',
            'approved'
        )->count();

        $rejectedRegistrations = NadiRegistration::where(
            'registration_status',
            'rejected'
        )->count();

        $statuses = NadiRegistration::query()
            ->select('registration_status')
            ->distinct()
            ->orderBy('registration_status')
            ->pluck('registration_status');

        return view('pages.management.registrations', compact(
            'registrations',
            'totalRegistrations',
            'pendingRegistrations',
            'approvedRegistrations',
            'rejectedRegistrations',
            'statuses'
        ));
    }
}
