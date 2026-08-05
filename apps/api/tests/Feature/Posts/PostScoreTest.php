<?php

use App\Jobs\RecomputePostScore;
use App\Models\Post;
use App\Services\Feed\ForYouScorer;
use Illuminate\Support\Facades\Queue;

test('publishing a post dispatches a score recompute', function () {
    Queue::fake();

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'hi'])
        ->assertCreated();

    Queue::assertPushed(RecomputePostScore::class);
});

test('the recompute job stores the score of a published post', function () {
    // Whole-second freeze: MySQL DATETIME truncates microseconds, and any
    // wall-clock drift between insert and scoring would break the tolerance.
    $this->freezeSecond();

    $post = Post::factory()->create([
        'likes_count' => 10,
        'comments_count' => 5,
        'published_at' => now()->subHours(6),
    ]);

    (new RecomputePostScore($post))->handle(app(ForYouScorer::class));

    expect($post->refresh()->score)->toEqualWithDelta((10 + 2 * 5) / pow(8, 1.6), 1e-9);
});

test('the recompute job leaves unpublished posts untouched', function () {
    $post = Post::factory()->draft()->create(['likes_count' => 10]);

    (new RecomputePostScore($post))->handle(app(ForYouScorer::class));

    expect($post->refresh()->score)->toBe(0.0);
});

test('posts:refresh-scores recomputes recent posts and skips old ones', function () {
    $this->freezeSecond();

    $recent = Post::factory()->create([
        'likes_count' => 20,
        'published_at' => now()->subHours(6),
    ]);
    $old = Post::factory()->create([
        'likes_count' => 20,
        'published_at' => now()->subHours(80),
    ]);
    $draft = Post::factory()->draft()->create(['likes_count' => 20]);

    $this->artisan('posts:refresh-scores')
        ->expectsOutputToContain('Refreshed scores for 1 post(s).')
        ->assertSuccessful();

    expect($recent->refresh()->score)->toEqualWithDelta(20 / pow(8, 1.6), 1e-9)
        ->and($old->refresh()->score)->toBe(0.0)
        ->and($draft->refresh()->score)->toBe(0.0);
});
