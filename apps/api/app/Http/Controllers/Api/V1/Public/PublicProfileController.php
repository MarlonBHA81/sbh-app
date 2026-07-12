<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Models\Profile;

class PublicProfileController extends Controller
{
    /**
     * Public, unauthenticated profile lookup for SSR metadata / SEO. Private
     * profiles and profiles owned by banned users are indistinguishable from
     * missing ones (404).
     */
    public function show(string $handle): PublicProfileResource
    {
        $profile = Profile::query()
            ->with(['user', 'businessCategory'])
            ->where('handle', mb_strtolower($handle))
            ->first();

        abort_if(
            $profile === null
                || $profile->is_private
                || $profile->user === null
                || $profile->user->isBanned(),
            404,
        );

        return new PublicProfileResource($profile);
    }
}
