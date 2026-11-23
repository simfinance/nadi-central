<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

use App\Models\NadiEnrollmentToken;
use App\Services\Agent\EnrollmentTokenService;

class EnrollmentTokenController extends Controller
{
    public function index(Request $request): View
    {
        $now = now();

        $query = NadiEnrollmentToken::query()
            ->with('creator');

        if ($request->filled('search'))
        {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query->where('token_name', 'like', "%{$search}%")
                    ->orWhere('environment_scope', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status'))
        {
            switch ($request->status)
            {
                case 'active':
                    $query->whereNull('used_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', $now);
                    break;

                case 'used':
                    $query->whereNotNull('used_at');
                    break;

                case 'revoked':
                    $query->whereNotNull('revoked_at');
                    break;

                case 'expired':
                    $query->whereNull('used_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '<=', $now);
                    break;
            }
        }

        $tokens = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $totalTokens = NadiEnrollmentToken::count();

        $activeTokens = NadiEnrollmentToken::query()
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->count();

        $usedTokens = NadiEnrollmentToken::whereNotNull('used_at')
            ->count();

        $revokedTokens = NadiEnrollmentToken::whereNotNull('revoked_at')
            ->count();

        $expiredTokens = NadiEnrollmentToken::query()
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', $now)
            ->count();

        return view('pages.management.enrollment-tokens', compact(
            'tokens',
            'totalTokens',
            'activeTokens',
            'usedTokens',
            'revokedTokens',
            'expiredTokens'
        ));
    }

    public function store(
        Request $request,
        EnrollmentTokenService $enrollmentTokenService
    )
    {
        $validated = $request->validate([
            'token_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'environment_scope' => [
                'nullable',
                'string',
                'max:50',
            ],
            'lifetime_hours' => [
                'required',
                'integer',
                'min:1',
                'max:720',
            ],
        ]);

        $result = $enrollmentTokenService->issue(
            $validated['token_name'] ?? null,
            $validated['environment_scope'] ?? null,
            (int) $validated['lifetime_hours'],
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Enrollment token created successfully.',
            'data' => [
                'token' => $result['token'],
                'token_name' => $result['record']->token_name,
                'environment_scope' => $result['record']->environment_scope,
                'expires_at' => $result['record']->expires_at->toIso8601String(),
            ],
        ], 201);
    }
}
