<?php

namespace App\\Http\\Controllers\\Auth;

use App\\Http\\Controllers\\Controller;
use App\\Models\\User;
use App\\Services\\Auth\\TotpService;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\RateLimiter;
use Illuminate\\View\\View;

class MfaController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user || ! $user->totp_enabled_at)
        {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-challenge', ['user' => $user]);
    }

    public function verify(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^(?:[0-9]{6}|[A-Fa-f0-9]{10})$/'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user || ! $user->totp_enabled_at || ! $user->totp_secret)
        {
            return redirect()->route('login');
        }

        $key = 'mfa:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5))
        {
            return back()->withErrors([
                'code' => 'Terlalu banyak percobaan. Silakan tunggu sebelum mencoba kembali.',
            ]);
        }

        $accepted = false;
        $step = null;

        DB::transaction(function () use ($request, $totp, $user, &$accepted, &$step)
        {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);

            if (! $lockedUser || ! $lockedUser->totp_enabled_at || ! $lockedUser->totp_secret)
            {
                return;
            }

            $secret = Crypt::decryptString($lockedUser->totp_secret);
            $step = $totp->verify($secret, $request->input('code'), $lockedUser->totp_last_used_step);

            if ($step !== null)
            {
                $lockedUser->forceFill(['totp_last_used_step' => $step])->save();
                $accepted = true;

                return;
            }

            $recoveryCode = strtoupper(trim($request->input('code')));

            $recovery = DB::table('user_mfa_recovery_codes')
                ->where('user_id', $lockedUser->id)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($recovery as $record)
            {
                if (Hash::check($recoveryCode, $record->code_hash))
                {
                    DB::table('user_mfa_recovery_codes')
                        ->where('id', $record->id)
                        ->update(['used_at' => now(), 'updated_at' => now()]);

                    $accepted = true;

                    $this->audit($request, $lockedUser, 'recovery_code_used');

                    return;
                }
            }
        });

        if (! $accepted)
        {
            RateLimiter::hit($key, 300);
            $this->audit($request, $user, 'login_failed');

            return back()->withErrors([
                'code' => 'Kode autentikasi tidak valid atau sudah pernah digunakan.',
            ]);
        }

        RateLimiter::clear($key);
        $this->completeLogin($request, $user);
        $this->audit($request, $user, $step === null ? 'recovery_login_succeeded' : 'login_succeeded');

        return redirect()->intended('/dashboard');
    }

    public function setup(Request $request, TotpService $totp): View|RedirectResponse
    {
        $user = $request->user() ?: $this->pendingUser($request);

        if (! $user)
        {
            return redirect()->route('login');
        }

        if ($user->totp_enabled_at)
        {
            return $request->user()
                ? redirect()->route('dashboard')
                : redirect()->route('mfa.challenge');
        }

        if (! $request->session()->has('mfa.enrollment_secret'))
        {
            $secret = $totp->generateSecret();
            $request->session()->put('mfa.enrollment_secret', Crypt::encryptString($secret));
        }
        else
        {
            $secret = Crypt::decryptString($request->session()->get('mfa.enrollment_secret'));
        }

        return view('auth.mfa-setup', [
            'user' => $user,
            'secret' => $secret,
            'provisioningUri' => $totp->provisioningUri($user->email, $secret),
        ]);
    }

    public function confirmSetup(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user() ?: $this->pendingUser($request);
        $encryptedSecret = $request->session()->get('mfa.enrollment_secret');

        if (! $user || ! $encryptedSecret)
        {
            return redirect()->route('login');
        }

        $secret = Crypt::decryptString($encryptedSecret);
        $step = $totp->verify($secret, $request->input('code'));

        if ($step === null)
        {
            return back()->withErrors([
                'code' => 'Kode tidak valid. Periksa waktu perangkat dan coba kembali.',
            ]);
        }

        $codes = [];

        DB::transaction(function () use ($user, $secret, $step, &$codes)
        {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->totp_enabled_at)
            {
                return;
            }

            $lockedUser->forceFill([
                'totp_secret' => Crypt::encryptString($secret),
                'totp_enabled_at' => now(),
                'totp_last_used_step' => $step,
            ])->save();

            DB::table('user_mfa_recovery_codes')
                ->where('user_id', $lockedUser->id)
                ->delete();

            for ($index = 0; $index < 10; $index++)
            {
                $code = strtoupper(bin2hex(random_bytes(5)));
                $codes[] = $code;

                DB::table('user_mfa_recovery_codes')->insert([
                    'user_id' => $lockedUser->id,
                    'code_hash' => Hash::make($code),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $request->session()->forget('mfa.enrollment_secret');

        if (! $request->user())
        {
            $this->completeLogin($request, $user);
        }
        else
        {
            $request->session()->regenerate();
        }

        $this->audit($request, $user, 'enrollment_succeeded');

        return redirect()
            ->route('mfa.recovery')
            ->with('recovery_codes', $codes);
    }

    public function recovery(Request $request): View|RedirectResponse
    {
        if (! $request->user())
        {
            return redirect()->route('login');
        }

        return view('auth.mfa-recovery');
    }

    public function finishRecovery(Request $request): RedirectResponse
    {
        if (! $request->user())
        {
            return redirect()->route('login');
        }

        return redirect()->route('dashboard');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('mfa.pending_user_id');
        $startedAt = (int) $request->session()->get('mfa.pending_at', 0);

        if (! $userId || ! $startedAt || now()->timestamp - $startedAt > 300)
        {
            $request->session()->forget([
                'mfa.pending_user_id',
                'mfa.pending_at',
                'mfa.enrollment_secret',
            ]);

            return null;
        }

        return User::query()->find($userId);
    }

    private function completeLogin(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget([
            'mfa.pending_user_id',
            'mfa.pending_at',
            'mfa.enrollment_secret',
        ]);
    }

    private function audit(Request $request, ?User $user, string $event): void
    {
        DB::table('user_mfa_audits')->insert([
            'user_id' => $user?->id,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
        ]);
    }
}
