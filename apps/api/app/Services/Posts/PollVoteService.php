<?php

namespace App\Services\Posts;

use App\Events\PollVoteTallied;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PollVoteService
{
    /**
     * Cast (or switch) the viewer's vote on a poll and return the fresh poll.
     */
    public function vote(Profile $voter, Post $post, int $optionId): Poll
    {
        $poll = $post->poll()->with('options')->first();

        abort_unless($poll !== null, 404, 'This post is not a poll.');

        if ($poll->hasEnded()) {
            throw ValidationException::withMessages([
                'option_id' => ['This poll has ended.'],
            ]);
        }

        $option = $poll->options->firstWhere('id', $optionId);

        if ($option === null) {
            throw ValidationException::withMessages([
                'option_id' => ['The selected option does not belong to this poll.'],
            ]);
        }

        DB::transaction(function () use ($poll, $voter, $option) {
            $existing = PollVote::query()
                ->where('poll_id', $poll->id)
                ->where('profile_id', $voter->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->option_id === $option->id) {
                return; // idempotent re-vote for the same option
            }

            if ($existing) {
                PollOption::query()->whereKey($existing->option_id)->where('votes_count', '>', 0)->decrement('votes_count');
                $existing->update(['option_id' => $option->id]);
            } else {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'option_id' => $option->id,
                    'profile_id' => $voter->id,
                ]);
                $poll->increment('votes_count');
            }

            PollOption::query()->whereKey($option->id)->increment('votes_count');
        });

        $poll = $poll->fresh(['options', 'post']);

        PollVoteTallied::dispatch($poll);

        $poll->viewerOptionId = $option->id;

        return $poll;
    }
}
