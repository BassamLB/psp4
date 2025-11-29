<?php

use App\Http\Middleware\EnsureUserIsAllowed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'user.allowed' => EnsureUserIsAllowed::class,
            'admin' => function ($request, $next) {
                if (! $request->user()?->isAdmin()) {
                    abort(403, '\u063a\u064a\u0631 \u0645\u0635\u0631\u062d \u0644\u0643 \u0628\u0627\u0644\u0648\u0635\u0648\u0644.');
                }

                return $next($request);
            },
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
