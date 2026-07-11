<?php

namespace App\Services\Posts\Handlers;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostTypeHandler;

class QuizHandler implements PostTypeHandler
{
    public function eagerLoad(): array
    {
        return ['quiz.questions'];
    }

    public function createSatellite(Post $post, array $payload): void
    {
        $quiz = $post->quiz()->create();

        foreach (array_values($payload['questions']) as $position => $question) {
            $quiz->questions()->create([
                'question' => $question['question'],
                'options' => array_values($question['options']),
                'correct_index' => (int) $question['correct_index'],
                'position' => $position,
            ]);
        }
    }

    public function updateSatellite(Post $post, array $payload): void
    {
        $post->quiz?->delete();
        $post->load('quiz.questions');

        $this->createSatellite($post, $payload);

        $post->load('quiz.questions');
    }

    public function present(Post $post, ?Profile $viewer): array
    {
        $quiz = $post->quiz;

        if ($quiz === null) {
            return [];
        }

        $attempt = $quiz->viewerAttempt;
        $attempted = $attempt !== null;

        return [
            'quiz' => [
                'attempts_count' => $quiz->attempts_count,
                'viewer_attempt' => $attempted
                    ? ['score_pct' => $attempt->score_pct, 'answers' => $attempt->answers]
                    : null,
                'questions' => $quiz->questions->map(function ($question) use ($attempted, $attempt) {
                    $data = [
                        'id' => $question->id,
                        'question' => $question->question,
                        'options' => $question->options,
                    ];

                    // correct_index (and the viewer's own answer) are only ever
                    // exposed once the viewer has attempted the quiz.
                    if ($attempted) {
                        $data['correct_index'] = $question->correct_index;
                        $data['viewer_answer'] = $attempt->answers[$question->position] ?? null;
                    }

                    return $data;
                })->all(),
            ],
        ];
    }
}
