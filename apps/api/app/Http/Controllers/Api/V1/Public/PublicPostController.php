<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPostResource;
use App\Models\Post;

class PublicPostController extends Controller
{
    /**
     * Public, unauthenticated post lookup for SSR metadata / SEO. Only
     * published, public posts by public, non-banned authors are exposed;
     * everything else is a 404. Hidden payloads (secret/magnifier) are never
     * serialised by the resource.
     */
    public function show(string $ulid): PublicPostResource
    {
        $post = Post::query()
            ->with(['profile.user', 'media', 'topics'])
            ->where('ulid', $ulid)
            ->first();

        abort_if(
            $post === null
                || ! $post->isPublished()
                || $post->visibility !== Post::VISIBILITY_PUBLIC
                || $post->profile === null
                || $post->profile->is_private
                || $post->profile->user === null
                || $post->profile->user->isBanned(),
            404,
        );

        return new PublicPostResource($post);
    }
}
