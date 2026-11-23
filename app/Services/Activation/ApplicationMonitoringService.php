<?php

namespace App\Services\Activation;

use App\Models\NadiActivationGrant;
use App\Models\NadiApplication;
use App\Models\NadiApprovalLog;
use App\Models\NadiRegistration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplicationMonitoringService
{
    public function __construct(
        private ActivationGrantService $activationGrantService
    ) {
    }

    public function suspend(
        string $applicationId,
        ?string $adminId,
        ?string $reason = null
    ): NadiApplication {
        return $this->changeSuspension(
            $applicationId,
            $adminId,
            $reason,
            true
        );
    }

    public function resume(
        string $applicationId,
        ?string $adminId,
        ?string $reason = null
    ): NadiApplication {
        return $this->changeSuspension(
            $applicationId,
            $adminId,
            $reason,
            false
        );
    }

    private function changeSuspension(
        string $applicationId,
        ?string $adminId,
        ?string $reason,
        bool $suspend
    ): NadiApplication {
        return DB::transaction(function () use (
            $applicationId,
            $adminId,
            $reason,
            $suspend
        ) {
            $application = NadiApplication::where(
                'id',
                $applicationId
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $application->registration_status !== 'approved'
                || $application->activation_status !== 'active'
            ) {
                throw new RuntimeException(
                    'Application is not approved and activated.'
                );
            }

            $isSuspended = $application->monitoring_suspended_at !== null;

            if ($suspend && $isSuspended) {
                throw new RuntimeException(
                    'Application monitoring is already suspended.'
                );
            }

            if (!$suspend && !$isSuspended) {
                throw new RuntimeException(
                    'Application monitoring is not suspended.'
                );
            }

            $registration = NadiRegistration::where(
                'application_id',
                $application->id
            )
                ->where('agent_id', $application->agent_id)
                ->where('registration_status', 'approved')
                ->orderByDesc('reviewed_at')
                ->lockForUpdate()
                ->first();

            if (!$registration) {
                throw new RuntimeException(
                    'Approved registration was not found.'
                );
            }

            $grant = NadiActivationGrant::where(
                'application_id',
                $application->id
            )
                ->where('agent_id', $application->agent_id)
                ->where('registration_id', $registration->id)
                ->where('grant_status', 'active')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('grant_version')
                ->lockForUpdate()
                ->first();

            if (
                !$grant
                || !$this->activationGrantService->verify($grant)
            ) {
                throw new RuntimeException(
                    'A valid activation grant is required.'
                );
            }

            $application->monitoring_suspended_at = $suspend
                ? now()
                : null;

            $application->save();

            NadiApprovalLog::create([
                'registration_id' => $registration->id,
                'application_id' => $application->id,
                'admin_id' => $adminId,
                'action' => $suspend
                    ? 'monitoring_suspended'
                    : 'monitoring_resumed',
                'reason' => $reason,
            ]);

            return $application->refresh();
        });
    }
}
