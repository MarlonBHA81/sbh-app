<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Event;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\EventRsvpService;
use App\Services\Posts\PollVoteService;
use App\Services\Posts\PostService;
use App\Services\Posts\QuizAttemptService;
use App\Support\ViewerReactions;
use App\Support\ViewerSatelliteState;
use Illuminate\Http\Request;

class PostInteractionController extends Controller
{
    public function pollVote(Request $request, Post $post, PollVoteService $service): PostResource
    {
        $actor = $this->actor($request, $post);

        $validated = $request->validate([
            'option_id' => ['required', 'integer'],
        ]);

        $service->vote($actor, $post, (int) $validated['option_id']);

        return $this->resource($post, $actor);
    }

    public function quizAttempt(Request $request, Post $post, QuizAttemptService $service): PostResource
    {
        $actor = $this->actor($request, $post);

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $service->attempt($actor, $post, array_map(
            fn ($v) => $v === null ? null : (int) $v,
            array_values($validated['answers']),
        ));

        return $this->resource($post, $actor);
    }

    public function rsvp(Request $request, Post $post, EventRsvpService $service): PostResource
    {
        $actor = $this->actor($request, $post);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', [Event::RSVP_GOING, Event::RSVP_INTERESTED, Event::RSVP_NONE])],
        ]);

        $service->rsvp($actor, $post, $validated['status']);

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
        ViewerSatelliteState::hydrate([$post], $actor);

        return new PostResource($post);
    }
}
