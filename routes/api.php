<?php

use App\Http\Controllers\AgentCredentialDeliveryController;
use App\Http\Controllers\Api\V1\Nadi\AgentControlController;
use App\Http\Controllers\Api\V1\Nadi\AgentEnrollmentController;
use App\Http\Controllers\Api\V1\Nadi\AgentActivationController;
use App\Http\Controllers\Api\V1\Nadi\AgentTelemetryController;
use App\Http\Controllers\Api\V1\Nadi\ReleaseManifestController;
use App\Http\Controllers\PrometheusMetricsController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NADI API Routes
|--------------------------------------------------------------------------
|
| API endpoints for NADI Agent communication.
|
*/

Route::get(
    '/internal/prometheus/metrics',
    PrometheusMetricsController::class
)->name('api.internal.prometheus.metrics');

Route::prefix('v1/nadi')->group(function ()
{
     /*
    |--------------------------------------------------------------------------
    | Agent Release Manifest
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/releases/manifest',
        [ReleaseManifestController::class, 'show']
    )->name('api.nadi.releases.manifest');

    /*
    |--------------------------------------------------------------------------
    | Agent Enrollment
    |--------------------------------------------------------------------------
    */

    Route::post('/agent/enroll', [
        AgentEnrollmentController::class,
        'enroll',
    ])
        ->middleware('throttle:10,1')
        ->name('api.nadi.agent.enroll');

    /*
    |--------------------------------------------------------------------------
    | Agent Control
    |--------------------------------------------------------------------------
    */

    Route::middleware('nadi.agent')
        ->prefix('agent')
        ->group(function ()
        {
            Route::post(
                '/control/poll',
                [AgentControlController::class, 'poll']
            )
                ->name('api.nadi.agent.control.poll');

            Route::post(
                '/commands/{commandId}/ack',
                [AgentControlController::class, 'acknowledge']
            )
                ->name('api.nadi.agent.commands.ack');

            Route::post(
                '/telemetry/batches',
                [AgentTelemetryController::class, 'store']
            )
                ->name('api.nadi.agent.telemetry.batches');

            Route::post(
                '/activation-grants/{grantId}/ack',
                [AgentActivationController::class, 'acknowledge']
            );

            Route::get(
                '/credentials/deliveries/{deliveryId}',
                [AgentCredentialDeliveryController::class, 'retrieve']
            )
                ->name('api.nadi.agent.credentials.retrieve');

            Route::post(
                '/credentials/deliveries/{deliveryId}/ack',
                [AgentCredentialDeliveryController::class, 'acknowledge']
            )
                ->name('api.nadi.agent.credentials.ack');
        });
});
