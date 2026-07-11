<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user) {
            $ulid = $request->header('X-Profile-Id');

            if ($ulid) {
                $profile = Profile::query()->where('ulid', $ulid)->first();

                if (! $profile || $profile->user_id !== $user->id) {
                    abort(403, 'The requested profile does not belong to you.');
                }
            } else {
                $profile = $user->personalProfile;
            }

            $request->attributes->set('activeProfile', $profile);

            if ($profile) {
                app()->instance('activeProfile', $profile);
            }
        }

        return $next($request);
    }
}
