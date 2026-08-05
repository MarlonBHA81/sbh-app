<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetActiveProfile;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Resolve request locale for all API routes (user pref → Accept-Language).
        $middleware->api(append: [SetLocale::class]);

        $middleware->alias([
            'not_banned' => EnsureNotBanned::class,
            'profile.active' => SetActiveProfile::class,
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report unhandled exceptions to Sentry when the package is installed
        // and a DSN is configured. Guarded so the app still boots if the SDK is
        // ever removed; inert (nothing sent) until a DSN is set in Integrations.
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
