<?php

namespace App\Support;

use App\Models\EventRsvp;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * Bulk-hydrates the viewer's satellite state (poll vote, quiz attempt, event
 * RSVP) onto a page of posts so PostResource avoids an N+1 lookup per post.
 *
 * State is stored on transient properties of the satellite models
 * (Poll::$viewerOptionId, Quiz::$viewerAttempt, Event::$viewerRsvp).
 */
class ViewerSatelliteState
{
    /**
     * @param  iterable<int, Post>  $posts
     */
    public static function hydrate(iterable $posts, ?Profile $viewer): void
    {
        if ($viewer === null) {
            return;
        }

        /** @var Collection<int, Post> $models */
        $models = collect($posts)->filter()->values();

        if ($models->isEmpty()) {
            return;
        }

        self::hydratePolls($models, $viewer);
        self::hydrateQuizzes($models, $viewer);
        self::hydrateEvents($models, $viewer);
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private static function hydratePolls(Collection $posts, Profile $viewer): void
    {
        $polls = $posts
            ->filter(fn (Post $p) => $p->relationLoaded('poll') && $p->poll !== null)
            ->map(fn (Post $p) => $p->poll)
            ->keyBy('id');

        if ($polls->isEmpty()) {
            return;
        }

        PollVote::query()
            ->where('profile_id', $viewer->id)
            ->whereIn('poll_id', $polls->keys()->all())
            ->get()
            ->each(function (PollVote $vote) use ($polls) {
                /** @var Poll $poll */
                $poll = $polls->get($vote->poll_id);
                $poll->viewerOptionId = $vote->option_id;
            });
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private static function hydrateQuizzes(Collection $posts, Profile $viewer): void
    {
        $quizzes = $posts
            ->filter(fn (Post $p) => $p->relationLoaded('quiz') && $p->quiz !== null)
            ->map(fn (Post $p) => $p->quiz)
            ->keyBy('id');

        if ($quizzes->isEmpty()) {
            return;
        }

        QuizAttempt::query()
            ->where('profile_id', $viewer->id)
            ->whereIn('quiz_id', $quizzes->keys()->all())
            ->get()
            ->each(function (QuizAttempt $attempt) use ($quizzes) {
                /** @var Quiz $quiz */
                $quiz = $quizzes->get($attempt->quiz_id);
                $quiz->viewerAttempt = $attempt;
            });
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private static function hydrateEvents(Collection $posts, Profile $viewer): void
    {
        $events = $posts
            ->filter(fn (Post $p) => $p->relationLoaded('event') && $p->event !== null)
            ->map(fn (Post $p) => $p->event)
            ->keyBy('id');

        if ($events->isEmpty()) {
            return;
        }

        EventRsvp::query()
            ->where('profile_id', $viewer->id)
            ->whereIn('event_id', $events->keys()->all())
            ->get()
            ->each(function (EventRsvp $rsvp) use ($events) {
                $events->get($rsvp->event_id)->viewerRsvp = $rsvp->status;
            });
    }
}
