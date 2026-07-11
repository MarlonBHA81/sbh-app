<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostService;
use App\Support\ViewerReactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function store(StorePostRequest $request, PostService $posts): JsonResponse
    {
        /** @var Profile|null $author */
        $author = $request->attributes->get('activeProfile');

        abort_unless($author, 403, 'An active profile is required to create posts.');

        $post = $posts->create($request->user(), $author, $request->validated());

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $this->ensureVisible($request, $post);

        $post->load(PostService::EAGER);

        ViewerReactions::hydrate([$post], $request->attributes->get('activeProfile'));

        return new PostResource($post);
    }

    public function update(UpdatePostRequest $request, Post $post, PostService $posts): PostResource
    {
        abort_unless($post->isAuthoredBy($request->user()), 403);

        return new PostResource($posts->update($request->user(), $post, $request->validated()));
    }

    public function destroy(Request $request, Post $post, PostService $posts): Response
    {
        abort_unless($post->isAuthoredBy($request->user()), 403);

        $posts->delete($post);

        return response()->noContent();
    }

    /**
     * Return the full (unmasked) payload for hidden-payload types such as
     * secret and magnifier. Counts as a view for now.
     */
    public function reveal(Request $request, Post $post): JsonResponse
    {
        $this->ensureVisible($request, $post);

        $post->increment('views_count');

        return response()->json([
            'data' => [
                'ulid' => $post->ulid,
                'type' => $post->type,
                'payload' => $post->payload,
                'views_count' => $post->views_count,
            ],
        ]);
    }

    private function ensureVisible(Request $request, Post $post): void
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($post->isVisibleTo($viewer), 404);
    }
}
