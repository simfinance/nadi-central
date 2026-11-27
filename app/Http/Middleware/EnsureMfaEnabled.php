<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->totp_secret || ! $user->totp_enabled_at))
        {
            if (! $request->routeIs('mfa.*', 'logout'))
            {
                return redirect()->route('mfa.setup');
            }
        }

        return $next($request);
    }
}
