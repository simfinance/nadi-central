<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NadiActivationGrant;
use App\Models\NadiRegistration;
use App\Services\Activation\ActivationGrantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationGrantController extends Controller
{
    public function index(): View
    {
        $registrations = NadiRegistration::with([
            'application.agent.server',
        ])
            ->where('registration_status', 'approved')
            ->orderByDesc('reviewed_at')
            ->get();

        $grants = NadiActivationGrant::with([
            'application',
            'agent',
            'registration',
        ])
            ->orderByDesc('issued_at')
            ->get();

        $grantsByRegistration = $grants
            ->groupBy('registration_id')
            ->map(function ($items)
            {
                return $items->first();
            });

        return view(
            'pages.management.activation-grants',
            compact(
                'registrations',
                'grants',
                'grantsByRegistration'
            )
        );
    }

    public function issue(
        string $registrationId,
        ActivationGrantService $activationGrantService
    ): RedirectResponse
    {
        $registration = NadiRegistration::findOrFail(
            $registrationId
        );

        if ($registration->registration_status !== 'approved')
        {
            return back()->with(
                'error',
                'Only approved registrations can be activated.'
            );
        }

        try
        {
            $grant = $activationGrantService->issue(
                $registration->id,
                auth()->id()
            );

            return back()->with(
                'success',
                'Activation grant issued successfully. Grant version: '
                    . $grant->grant_version
            );
        }
        catch (\Throwable $exception)
        {
            report($exception);

            return back()->with(
                'error',
                'Failed to issue activation grant.'
            );
        }
    }

    public function revoke(
        Request $request,
        string $grantId,
        ActivationGrantService $activationGrantService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $grant = NadiActivationGrant::findOrFail(
            $grantId
        );

        try
        {
            $activationGrantService->revoke(
                $grant->id,
                auth()->id(),
                $validated['reason'] ?? null
            );

            return back()->with(
                'success',
                'Activation grant revoked successfully.'
            );
        }
        catch (\Throwable $exception)
        {
            report($exception);

            return back()->with(
                'error',
                'Failed to revoke activation grant.'
            );
        }
    }
}
