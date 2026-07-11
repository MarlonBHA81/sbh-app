<?php

namespace App\Services\Posts;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

class QuizAttemptService
{
    /**
     * Record the viewer's single attempt at a quiz and return it with score.
     *
     * @param  list<int>  $answers  answer indexes aligned to question order
     */
    public function attempt(Profile $profile, Post $post, array $answers): QuizAttempt
    {
        /** @var Quiz|null $quiz */
        $quiz = $post->quiz()->with('questions')->first();

        abort_unless($quiz !== null, 404, 'This post is not a quiz.');

        $alreadyAttempted = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('profile_id', $profile->id)
            ->exists();

        abort_if($alreadyAttempted, 409, 'You have already attempted this quiz.');

        $questions = $quiz->questions;
        $total = $questions->count();

        $correct = 0;
        $normalised = [];

        foreach ($questions->values() as $i => $question) {
            $given = $answers[$i] ?? null;
            $normalised[$i] = $given;

            if ($given !== null && (int) $given === (int) $question->correct_index) {
                $correct++;
            }
        }

        $score = $total > 0 ? (int) round($correct / $total * 100) : 0;

        return DB::transaction(function () use ($quiz, $profile, $normalised, $score) {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'profile_id' => $profile->id,
                'answers' => $normalised,
                'score_pct' => $score,
            ]);

            $quiz->increment('attempts_count');

            return $attempt;
        });
    }
}
