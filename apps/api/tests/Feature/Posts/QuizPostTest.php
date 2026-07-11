<?php

use App\Models\Quiz;

function createQuiz($test, $user): string
{
    return $test->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'quiz',
        'payload' => ['questions' => [
            ['question' => '2 + 2?', 'options' => ['3', '4', '5'], 'correct_index' => 1],
            ['question' => 'Capital of France?', 'options' => ['Paris', 'Rome'], 'correct_index' => 0],
        ]],
    ])->json('data.ulid');
}

test('a quiz is created and hides correct answers before an attempt', function () {
    $author = userWithProfile();
    $viewer = userWithProfile();
    $ulid = createQuiz($this, $author);

    $this->actingAs($viewer)->getJson("/api/v1/posts/{$ulid}")
        ->assertOk()
        ->assertJsonPath('data.type', 'quiz')
        ->assertJsonCount(2, 'data.quiz.questions')
        ->assertJsonPath('data.quiz.viewer_attempt', null)
        ->assertJsonMissingPath('data.quiz.questions.0.correct_index');
});

test('a quiz rejects a correct_index outside the options', function () {
    $author = userWithProfile();

    $this->actingAs($author)->postJson('/api/v1/posts', [
        'type' => 'quiz',
        'payload' => ['questions' => [
            ['question' => 'Q', 'options' => ['a', 'b'], 'correct_index' => 3],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.questions.0.correct_index');
});

test('attempting a quiz scores the answers and reveals correct answers', function () {
    $author = userWithProfile();
    $viewer = userWithProfile();
    $ulid = createQuiz($this, $author);

    // One right (2+2=4 => index 1), one wrong (index 1 instead of 0).
    $this->actingAs($viewer)->postJson("/api/v1/posts/{$ulid}/quiz-attempt", ['answers' => [1, 1]])
        ->assertOk()
        ->assertJsonPath('data.quiz.viewer_attempt.score_pct', 50)
        ->assertJsonPath('data.quiz.attempts_count', 1)
        ->assertJsonPath('data.quiz.questions.0.correct_index', 1)
        ->assertJsonPath('data.quiz.questions.0.viewer_answer', 1);
});

test('a perfect quiz attempt scores 100', function () {
    $author = userWithProfile();
    $viewer = userWithProfile();
    $ulid = createQuiz($this, $author);

    $this->actingAs($viewer)->postJson("/api/v1/posts/{$ulid}/quiz-attempt", ['answers' => [1, 0]])
        ->assertOk()
        ->assertJsonPath('data.quiz.viewer_attempt.score_pct', 100);
});

test('a quiz allows only a single attempt', function () {
    $author = userWithProfile();
    $viewer = userWithProfile();
    $ulid = createQuiz($this, $author);

    $this->actingAs($viewer)->postJson("/api/v1/posts/{$ulid}/quiz-attempt", ['answers' => [1, 0]])->assertOk();
    $this->actingAs($viewer)->postJson("/api/v1/posts/{$ulid}/quiz-attempt", ['answers' => [0, 0]])
        ->assertStatus(409);

    expect(Quiz::first()->attempts_count)->toBe(1);
});

test('the author sees correct answers only after attempting', function () {
    $author = userWithProfile();
    $ulid = createQuiz($this, $author);

    // Author has not attempted -> hidden.
    $this->actingAs($author)->getJson("/api/v1/posts/{$ulid}")
        ->assertJsonMissingPath('data.quiz.questions.0.correct_index');
});
