<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a super-admin feature flag. When the flag is off the
 * route responds 404 — the surface simply does not exist for the client, which
 * mirrors how the web app hides it. Usage: ->middleware('feature:shop').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        abort_unless(Features::enabled($key), 404);

        return $next($request);
    }
}
