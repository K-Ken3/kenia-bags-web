<?php

use App\Http\Middleware\EnforceTrustedHosts;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NormalizeProxyHeaders;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Monitoring;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
)
    ->withMiddleware(function (Middleware $middleware): void {
        // Prefer explicit proxy addresses when the platform publishes them.
        // Railway does not publish stable edge ranges, so the fallback below
        // normalizes only the rightmost X-Forwarded-For value. Direct access to
        // the app must be blocked at the network edge for this to be safe.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->prepend(NormalizeProxyHeaders::class);

        $middleware->web(append: [
            EnforceTrustedHosts::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);

        // Payment providers post from their own servers and cannot send a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            app(Monitoring::class)->reportException($exception);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
