<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user && $user->isBanned()) {
            return response()->json([
                'message' => __('Your account has been banned.'),
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        return $next($request);
    }
}
