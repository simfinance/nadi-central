<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate user using username and password.
     */
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

        if (! Auth::attempt([
            'username' => $username,
            'password' => $request->input('password'),
        ]))
        {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => 'Username atau password salah.',
                ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Logout authenticated user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
