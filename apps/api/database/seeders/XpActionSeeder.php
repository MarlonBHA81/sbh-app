<?php

namespace Database\Seeders;

use App\Models\XpAction;
use App\Services\Gamification\GamificationService as G;
use Illuminate\Database\Seeder;

class XpActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            [G::POST_PUBLISHED, 'Published a post', 10, 5],
            [G::COMMENT_CREATED, 'Wrote a comment', 5, 20],
            [G::LIKE_RECEIVED, 'Received a like', 2, 50],
            [G::UPVOTE_RECEIVED, 'Received an upvote', 2, 50],
            [G::FOLLOWER_GAINED, 'Gained a follower', 5, 20],
            [G::POLL_VOTE_CAST, 'Voted in a poll', 1, 10],
            [G::QUIZ_COMPLETED, 'Completed a quiz', 3, 10],
        ];

        foreach ($actions as [$key, $label, $points, $cap]) {
            XpAction::query()->updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'points' => $points, 'daily_cap' => $cap],
            );
        }
    }
}
