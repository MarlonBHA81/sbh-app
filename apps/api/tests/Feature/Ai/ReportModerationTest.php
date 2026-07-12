<?php

use App\Models\Post;
use App\Models\Report;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;
use Tests\Support\FakeAiGateway;

test('creating a report stores an AI assessment when the gateway is enabled', function () {
    app()->instance(AiGateway::class, new FakeAiGateway(
        enabled: true,
        moderation: new AiModerationResult(
            flagged: true,
            categories: ['harassment'],
            confidence: 0.72,
            summary: 'Targeted insult.',
        ),
    ));

    $reporter = userWithProfile(['handle' => 'moderation_reporter']);
    $post = Post::factory()->create(['body' => 'you are terrible']);

    $this->actingAs($reporter)->postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_ulid' => $post->ulid,
        'category' => 'harassment',
    ])->assertCreated();

    $report = Report::query()->firstOrFail();

    expect($report->ai_assessment)->toMatchArray([
        'flagged' => true,
        'categories' => ['harassment'],
        'confidence' => 0.72,
        'summary' => 'Targeted insult.',
    ]);
});

test('creating a report leaves the AI assessment null when the gateway is disabled', function () {
    app()->instance(AiGateway::class, new FakeAiGateway(enabled: false));

    $reporter = userWithProfile(['handle' => 'no_ai_reporter']);
    $post = Post::factory()->create();

    $this->actingAs($reporter)->postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_ulid' => $post->ulid,
        'category' => 'spam',
    ])->assertCreated();

    expect(Report::query()->firstOrFail()->ai_assessment)->toBeNull();
});
