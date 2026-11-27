<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class GrafanaDashboardController extends Controller
{
    public function index(): View
    {
        $dashboardUid = (string) config('nadi.grafana.dashboard_uid', '');
        $dashboardUidIsValid = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $dashboardUid) === 1;
        $grafanaReachable = false;

        try
        {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(3)
                ->get(rtrim((string) config('nadi.grafana.internal_url'), '/') . '/api/health');

            $grafanaReachable = $response->successful()
                && $response->json('database') === 'ok';
        }
        catch (Throwable)
        {
            $grafanaReachable = false;
        }

        $publicPath = (string) config('nadi.grafana.public_path', '/grafana/');

        if (preg_match('#^/[A-Za-z0-9/_-]*/$#', $publicPath) !== 1)
        {
            $publicPath = '/grafana/';
        }

        $iframeUrl = null;

        if ($dashboardUidIsValid && $grafanaReachable)
        {
            $iframeUrl = rtrim($publicPath, '/')
                . '/d/'
                . rawurlencode($dashboardUid)
                . '/nadi-overview?orgId='
                . max(1, (int) config('nadi.grafana.org_id', 1))
                . '&kiosk';
        }

        return view('pages.monitoring.grafana', [
            'grafanaReachable' => $grafanaReachable,
            'dashboardConfigured' => $dashboardUidIsValid,
            'iframeUrl' => $iframeUrl,
        ]);
    }

    public function authenticateProxy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user)
        {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        if (! $user->totp_secret || ! $user->totp_enabled_at)
        {
            return response()->json(['status' => 'mfa_required'], 403);
        }

        return response()
            ->json(['status' => 'authenticated'])
            ->header('X-NADI-Grafana-User', 'nadi-' . $user->getAuthIdentifier())
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
    }
}
