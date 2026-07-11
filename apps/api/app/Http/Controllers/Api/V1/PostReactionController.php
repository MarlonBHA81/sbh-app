<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Engagement\ReactionService;
use App\Services\Posts\PostService;
use App\Support\ViewerReactions;
use Illuminate\Http\Request;

class PostReactionController extends Controller
{
    public function __construct(private ReactionService $reactions) {}

    public function like(Request $request, Post $post): PostResource
    {
        $actor = $this->actor($request, $post);

        $this->reactions->like($actor, $post);

        return $this->resource($post, $actor);
    }

    public function unlike(Request $request, Post $post): PostResource
    {
        $actor = $this->actor($request, $post);

        $this->reactions->unlike($actor, $post);

        return $this->resource($post, $actor);
    }

    public function vote(Request $request, Post $post): PostResource
    {
        $actor = $this->actor($request, $post);

        $validated = $request->validate([
            'value' => ['required', 'integer', 'in:1,-1,0'],
        ]);

        $this->reactions->vote($actor, $post, (int) $validated['value']);

        return $this->resource($post, $actor);
    }

    private function actor(Request $request, Post $post): Profile
    {
        $actor = $request->attributes->get('activeProfile');

        abort_unless($actor instanceof Profile, 422, 'No active profile.');
        abort_unless($post->isVisibleTo($actor), 404);

        return $actor;
    }

    private function resource(Post $post, Profile $actor): PostResource
    {
        $post = $post->fresh()->load(PostService::EAGER);

        ViewerReactions::hydrate([$post], $actor);

        return new PostResource($post);
    }
}
