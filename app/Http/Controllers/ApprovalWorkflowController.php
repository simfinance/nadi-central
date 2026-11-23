<?php

namespace App\Http\Controllers;

use App\Models\NadiApplication;
use App\Models\NadiApprovalLog;
use App\Models\NadiRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $query = NadiRegistration::with([
            'application.agent.server',
            'agent.server',
            'reviewer',
        ]);

        $search = trim($request->search ?? '');

        if ($search !== '')
        {
            $query->where(function ($q) use ($search)
            {
                $q->where('id', 'like', '%' . $search . '%')
                    ->orWhereHas('application', function ($appQuery) use ($search)
                    {
                        $appQuery->where(
                            'application_name',
                            'like',
                            '%' . $search . '%'
                        )
                            ->orWhere('application_key', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('agent', function ($agentQuery) use ($search)
                    {
                        $agentQuery->where(
                            'agent_instance_id',
                            'like',
                            '%' . $search . '%'
                        );
                    });
            });
        }

        $status = $request->status;

        if ($status && in_array($status, [
            'pending',
            'approved',
            'rejected',
        ]))
        {
            $query->where('registration_status', $status);
        }

        $registrations = $query
            ->orderByRaw("
                CASE
                    WHEN registration_status = 'pending' THEN 0
                    ELSE 1
                END
            ")
            ->orderByDesc('requested_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => NadiRegistration::count(),

            'pending' => NadiRegistration::where(
                'registration_status',
                'pending'
            )->count(),

            'approved' => NadiRegistration::where(
                'registration_status',
                'approved'
            )->count(),

            'rejected' => NadiRegistration::where(
                'registration_status',
                'rejected'
            )->count(),
        ];

        return view(
            'pages.management.approval-workflow',
            compact(
                'registrations',
                'stats',
                'search',
                'status'
            )
        );
    }

    public function approve($id)
    {
        $result = DB::transaction(function () use ($id)
        {
            $registration = NadiRegistration::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->registration_status !== 'pending')
            {
                return false;
            }

            $application = NadiApplication::where(
                'id',
                $registration->application_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $registration->registration_status = 'approved';
            $registration->reviewed_at = now();
            $registration->reviewed_by = auth()->id();
            $registration->rejection_reason = null;
            $registration->save();

            $application->registration_status = 'approved';
            $application->activation_status = 'pending';
            $application->monitoring_status = 'dormant';
            $application->save();

            NadiApprovalLog::create([
                'registration_id' => $registration->id,
                'application_id' => $registration->application_id,
                'admin_id' => auth()->id(),
                'action' => 'approved',
                'reason' => null,
            ]);

            return true;
        });

        if (!$result)
        {
            return redirect()
                ->route('management.approval-workflow')
                ->with('error', 'Registrasi sudah diproses sebelumnya.');
        }

        return redirect()
            ->route('management.approval-workflow')
            ->with('success', 'Registrasi berhasil disetujui.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:5000',
            ],
        ]);

        $result = DB::transaction(function () use ($id, $validated)
        {
            $registration = NadiRegistration::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->registration_status !== 'pending')
            {
                return false;
            }

            $application = NadiApplication::where(
                'id',
                $registration->application_id
            )
                ->lockForUpdate()
                ->firstOrFail();

            $registration->registration_status = 'rejected';
            $registration->reviewed_at = now();
            $registration->reviewed_by = auth()->id();
            $registration->rejection_reason = $validated['reason'];
            $registration->save();

            $application->registration_status = 'rejected';
            $application->monitoring_status = 'dormant';
            $application->save();

            NadiApprovalLog::create([
                'registration_id' => $registration->id,
                'application_id' => $registration->application_id,
                'admin_id' => auth()->id(),
                'action' => 'rejected',
                'reason' => $validated['reason'],
            ]);

            return true;
        });

        if (!$result)
        {
            return redirect()
                ->route('management.approval-workflow')
                ->with('error', 'Registrasi sudah diproses sebelumnya.');
        }

        return redirect()
            ->route('management.approval-workflow')
            ->with('success', 'Registrasi berhasil ditolak.');
    }
}
