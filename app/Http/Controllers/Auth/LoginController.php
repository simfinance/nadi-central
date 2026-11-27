<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $username = strtolower(trim($request->input('username')));
        $throttleKey = Str::lower($username) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5))
        {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => "Terlalu banyak percobaan login. Silakan coba kembali dalam {$seconds} detik.",
                ]);
        }

        $user = User::query()
            ->where('username', $username)
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password))
        {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => 'Username atau password salah.',
                ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::logout();
        $request->session()->forget([
            'mfa.pending_user_id',
            'mfa.pending_at',
            'mfa.enrollment_secret',
        ]);
        $request->session()->regenerate();

        $request->session()->put([
            'mfa.pending_user_id' => $user->id,
            'mfa.pending_at' => now()->timestamp,
        ]);

        if (! $user->totp_secret || ! $user->totp_enabled_at)
        {
            return redirect()->route('mfa.setup');
        }

        return redirect()->route('mfa.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
