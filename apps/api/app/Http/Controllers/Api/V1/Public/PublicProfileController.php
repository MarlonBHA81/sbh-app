<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPostResource;
use App\Http\Resources\PublicProfileResource;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    /**
     * Public, published posts for a public profile — read-only browsing for
     * logged-out visitors and crawlers. Same wall as show().
     */
    public function posts(string $handle): AnonymousResourceCollection
    {
        $profile = Profile::query()
            ->with('user')
            ->where('handle', mb_strtolower($handle))
            ->first();

        abort_if(
            $profile === null
                || $profile->is_private
                || $profile->user === null
                || $profile->user->isBanned(),
            404,
        );

        $posts = Post::query()
            ->with(['profile.user', 'media', 'topics'])
            ->where('profile_id', $profile->id)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->latest('published_at')
            ->latest('id')
            ->cursorPaginate(20);

        return PublicPostResource::collection($posts);
    }
}
