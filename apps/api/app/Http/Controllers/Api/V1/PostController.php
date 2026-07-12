<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Ai\AiGateway;
use App\Services\Analytics\PostStatsService;
use App\Services\Posts\PostService;
use App\Support\ViewerReactions;
use App\Support\ViewerSatelliteState;
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

        $viewer = $request->attributes->get('activeProfile');
        ViewerReactions::hydrate([$post], $viewer);
        ViewerSatelliteState::hydrate([$post], $viewer);

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
    public function reveal(Request $request, Post $post, PostStatsService $stats): JsonResponse
    {
        $this->ensureVisible($request, $post);

        $post->increment('views_count');
        $stats->bump($post, PostStatsService::VIEWS);

        return response()->json([
            'data' => [
                'ulid' => $post->ulid,
                'type' => $post->type,
                'payload' => $post->payload,
                'views_count' => $post->views_count,
            ],
        ]);
    }

    /**
     * Suggest topic slugs for draft post text via the AI gateway. Returns an
     * empty list when AI is disabled, which the composer treats as a no-op.
     */
    public function suggestTopics(Request $request, AiGateway $ai): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => ['slugs' => $ai->suggestTopics($data['text'])],
        ]);
    }

    private function ensureVisible(Request $request, Post $post): void
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($post->isVisibleTo($viewer), 404);
    }
}
