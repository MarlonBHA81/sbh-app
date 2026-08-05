<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to admins. Campaign management (creating/editing promoted
 * post campaigns) is an admin-operated surface; tracking and sponsor slots
 * remain open to every authenticated user.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_admin, 403, 'Admin access required.');

        return $next($request);
    }
}
