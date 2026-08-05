<?php

use App\Models\Post;
use App\Models\Report;

test('a user can report a post', function () {
    $reporter = userWithProfile(['handle' => 'reporter_one']);
    $post = Post::factory()->create();

    $this->actingAs($reporter)
        ->postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_ulid' => $post->ulid,
            'category' => 'spam',
            'details' => 'This is spam',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['ulid', 'status']);

    expect(Report::count())->toBe(1);
});

test('report validation rejects unknown category and type', function () {
    $reporter = userWithProfile();
    $post = Post::factory()->create();

    $this->actingAs($reporter)
        ->postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_ulid' => $post->ulid,
            'category' => 'not_a_category',
        ])
        ->assertUnprocessable();

    $this->actingAs($reporter)
        ->postJson('/api/v1/reports', [
            'reportable_type' => 'widget',
            'reportable_ulid' => $post->ulid,
            'category' => 'spam',
        ])
        ->assertUnprocessable();
});

test('a duplicate open report returns the existing one with 200', function () {
    $reporter = userWithProfile(['handle' => 'dupe_reporter']);
    $post = Post::factory()->create();

    $first = $this->actingAs($reporter)->postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_ulid' => $post->ulid,
        'category' => 'spam',
    ])->assertCreated();

    $second = $this->actingAs($reporter)->postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_ulid' => $post->ulid,
        'category' => 'harassment',
    ])->assertOk();

    expect(Report::count())->toBe(1)
        ->and($second->json('ulid'))->toBe($first->json('ulid'));
});

test('reporting a missing subject returns 404', function () {
    $reporter = userWithProfile();

    $this->actingAs($reporter)
        ->postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_ulid' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
            'category' => 'spam',
        ])
        ->assertNotFound();
});

test('reporting content the reporter cannot see returns 404', function () {
    $reporter = userWithProfile(['handle' => 'blind_reporter']);
    $owner = userWithProfile(['handle' => 'hidden_owner']);

    $hiddenPost = Post::factory()->draft()->create(['profile_id' => $owner->personalProfile->id]);

    $this->actingAs($reporter)
        ->postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_ulid' => $hiddenPost->ulid,
            'category' => 'spam',
        ])
        ->assertNotFound();

    expect(Report::count())->toBe(0);
});

test('the reports endpoint is rate limited', function () {
    $reporter = userWithProfile(['handle' => 'spammy_reporter']);

    // 10 distinct posts within the 10/min window succeed; the 11th is throttled.
    foreach (range(1, 10) as $i) {
        $post = Post::factory()->create();
        $this->actingAs($reporter)->postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_ulid' => $post->ulid,
            'category' => 'spam',
        ])->assertCreated();
    }

    $extra = Post::factory()->create();
    $this->actingAs($reporter)->postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_ulid' => $extra->ulid,
        'category' => 'spam',
    ])->assertStatus(429);
});
