<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Foundation\\Configuration\\Exceptions;
use Illuminate\\Foundation\\Configuration\\Middleware;
use Illuminate\\Http\\Middleware\\TrustProxies;
use Illuminate\\Http\\Request;

use App\\Http\\Middleware\\NadiAgentAuthentication;
use App\\Http\\Middleware\\EnsureMfaEnabled;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void
    {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR,
        );

        $middleware->alias([
            'nadi.agent' => NadiAgentAuthentication::class,
            'mfa' => EnsureMfaEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->booted(function (Application $app): void
    {
        $trustedProxies = $app['config']->get(
            'app.trusted_proxies',
            [],
        );

        TrustProxies::at($trustedProxies);
    })
    ->create();
