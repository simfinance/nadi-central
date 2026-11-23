<?php

namespace App\Http\Controllers;

use App\Models\NadiApplication;
use App\Services\Activation\ApplicationMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ApplicationLifecycleController extends Controller
{
    public function suspend(
        Request $request,
        string $applicationId,
        ApplicationMonitoringService $monitoringService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        try {
            $monitoringService->suspend(
                $applicationId,
                auth()->id(),
                $validated['reason'] ?? null
            );

            return back()->with(
                'success',
                'Application monitoring suspended successfully.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Failed to suspend application monitoring.'
            );
        }
    }

    public function resume(
        Request $request,
        string $applicationId,
        ApplicationMonitoringService $monitoringService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        try {
            $monitoringService->resume(
                $applicationId,
                auth()->id(),
                $validated['reason'] ?? null
            );

            return back()->with(
                'success',
                'Application monitoring resumed successfully.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Failed to resume application monitoring.'
            );
        }
    }
}