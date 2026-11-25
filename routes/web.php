<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CentralHealthController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\PrometheusQueryController;
use App\Http\Controllers\PrometheusMonitoringController;
use App\Http\Controllers\GrafanaDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\EnrollmentTokenController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\ActivationGrantController;
use App\Http\Controllers\AgentCredentialController;
use App\Http\Controllers\ApplicationLifecycleController;
use App\Http\Controllers\TelemetryRetentionPolicyController;
use App\Http\Controllers\NotificationMailSettingsController;
use App\Http\Controllers\NotificationDeliveryController;
use App\Http\Controllers\NotificationTemplateController;

Route::get('/', function () {
    if (Auth::check())
    {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');
});

Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
Route::post('/mfa/setup', [MfaController::class, 'confirmSetup'])->name('mfa.setup.confirm');
Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
Route::post('/mfa/challenge', [MfaController::class, 'verify'])->name('mfa.challenge.verify');
Route::get('/mfa/recovery', [MfaController::class, 'recovery'])->name('mfa.recovery');

Route::get('/internal/grafana/auth', [GrafanaDashboardController::class, 'authenticateProxy'])
    ->name('internal.grafana.auth');
Route::post('/mfa/recovery/continue', [MfaController::class, 'finishRecovery'])->name('mfa.recovery.finish');

Route::middleware(['auth', 'mfa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/central-health', [CentralHealthController::class, 'index'])
        ->name('monitoring.central-health');

    Route::get('/central-health/network-history', [CentralHealthController::class, 'networkHistory'])
        ->name('monitoring.central-health.network-history');

    Route::prefix('monitoring')
        ->name('monitoring.')
        ->group(function () {
            Route::get('/servers', [ServerController::class, 'index'])->name('servers');

            Route::get('/infrastructure', [PrometheusMonitoringController::class, 'index'])
                ->name('infrastructure');

            Route::get('/grafana', [GrafanaDashboardController::class, 'index'])
                ->name('grafana');

            Route::prefix('prometheus')
                ->name('prometheus.')
                ->group(function () {
                    Route::get('/overview', [PrometheusQueryController::class, 'overview'])
                        ->name('overview');

                    Route::get('/metrics', [PrometheusQueryController::class, 'metrics'])
                        ->name('metrics');

                    Route::get('/query/{metric}', [PrometheusQueryController::class, 'query'])
                        ->where('metric', '[a-z0-9_]+')
                        ->name('query');
                });

            Route::get('/applications', [ApplicationController::class, 'index'])->name('applications');
            Route::post('/applications/{applicationId}/resume',[ApplicationLifecycleController::class, 'resume'])->name('applications.resume');
            Route::post('/applications/{applicationId}/suspend',[ApplicationLifecycleController::class, 'suspend'])->name('applications.suspend');

            Route::get('/databases', function () {
                return view('pages.placeholder', [
                    'pageTitle' => 'Databases',
                ]);
            })->name('databases');
        });

    Route::prefix('management')
        ->name('management.')
        ->group(function () {

            Route::get('/agents', [AgentController::class, 'index'])->name('agents');
            Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations');

            Route::get('/enrollment-tokens', [EnrollmentTokenController::class, 'index'])->name('enrollment-tokens');
            Route::post('/enrollment-tokens',[EnrollmentTokenController::class, 'store'])->name('enrollment-tokens.store');

            Route::get('/approval-workflow', [ApprovalWorkflowController::class, 'index'])->name('approval-workflow');
            Route::post('/approval-workflow/{id}/reject', [ApprovalWorkflowController::class, 'reject'])->name('approval-workflow.reject');
            Route::post('/approval-workflow/{id}/approve', [ApprovalWorkflowController::class, 'approve'])->name('approval-workflow.approve');

            Route::get('/activation-grants', [ActivationGrantController::class, 'index'])->name('activation-grants');
            Route::post('/activation-grants/{registrationId}/issue', [ActivationGrantController::class, 'issue'])->name('activation-grants.issue');
            Route::post('/activation-grants/{grantId}/revoke', [ActivationGrantController::class, 'revoke'])->name('activation-grants.revoke');

            Route::get('/agents/{agent}/credentials', [AgentCredentialController::class, 'index'])->name('agent-credentials.index');
            Route::post('/agents/{agent}/credentials', [AgentCredentialController::class, 'issue'])->name('agent-credentials.issue');
            Route::post('/agents/{agent}/credentials/{credential}/revoke',[AgentCredentialController::class, 'revoke'])->name('agent-credentials.revoke');

            Route::post('/agents/{agent}/credentials/rotate', [AgentCredentialController::class, 'rotate'])->name('agent-credentials.rotate');

            Route::get('/telemetry-retention', [TelemetryRetentionPolicyController::class, 'index'])->name('telemetry-retention');
            Route::post('/telemetry-retention', [TelemetryRetentionPolicyController::class, 'update'])->name('telemetry-retention.update');

            Route::get('/notification-mail', [NotificationMailSettingsController::class, 'index'])->name('notification-mail');
            Route::put('/notification-mail', [NotificationMailSettingsController::class, 'update'])->name('notification-mail.update');
            Route::post('/notification-mail/test', [NotificationMailSettingsController::class, 'sendTest'])->name('notification-mail.test');

            Route::get('/notifications', [NotificationDeliveryController::class, 'index'])->name('notifications');
            Route::post('/notifications', [NotificationDeliveryController::class, 'store'])->name('notifications.store');

            Route::get('/notification-templates', [NotificationTemplateController::class, 'index'])->name('notification-templates');
            Route::post('/notification-templates', [NotificationTemplateController::class, 'store'])->name('notification-templates.store');
            Route::put('/notification-templates/{templateId}', [NotificationTemplateController::class, 'update'])->whereNumber('templateId')->name('notification-templates.update');
            Route::delete('/notification-templates/{templateId}', [NotificationTemplateController::class, 'destroy'])->whereNumber('templateId')->name('notification-templates.destroy');

            // Ringkasan seluruh server & aplikasi
            Route::get('/telemetry', [TelemetryController::class, 'index'])
                ->name('telemetry');

            Route::get('/telemetry/overview', [TelemetryController::class, 'overview'])
                ->name('telemetry.overview');

            Route::get('/monitoring/infrastructure/data', [TelemetryController::class, 'infrastructureData'])
                ->name('infrastructure.data');

            // Detail per server
            Route::get('/telemetry/data', [TelemetryController::class, 'data'])
                ->name('telemetry.data');

            Route::get('/telemetry/records/{id}', [TelemetryController::class, 'record'])
                ->whereUuid('id')
                ->name('telemetry.record');

            Route::get('/telemetry/{agent}', [TelemetryController::class, 'show'])
                ->whereUuid('agent')
                ->name('telemetry.show');
            
        });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});