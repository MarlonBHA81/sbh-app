<?php

use App\Models\Post;
use App\Services\Feed\ForYouScorer;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

function scorablePost(array $attributes): Post
{
    return (new Post)->forceFill(array_merge([
        'likes_count' => 0,
        'comments_count' => 0,
        'reposts_count' => 0,
        'views_count' => 0,
        'upvotes_count' => 0,
        'downvotes_count' => 0,
        'status' => Post::STATUS_PUBLISHED,
    ], $attributes));
}

test('score matches the known formula for known counts', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    // engagement = 10 + 2*5 + 3*2 + 1.5*(4-1) + 0.1*ln(100) = 30.960...
    // decay = (6 + 2)^1.6 = 27.857...
    $post = scorablePost([
        'likes_count' => 10,
        'comments_count' => 5,
        'reposts_count' => 2,
        'upvotes_count' => 4,
        'downvotes_count' => 1,
        'views_count' => 99,
        'published_at' => now()->subHours(6),
    ]);

    $expected = (10 + 2 * 5 + 3 * 2 + 1.5 * 3 + 0.1 * log(100)) / pow(8, 1.6);

    expect(app(ForYouScorer::class)->score($post))
        ->toEqualWithDelta($expected, 1e-9)
        ->toEqualWithDelta(1.1113, 0.0005);
});

test('a post with no engagement scores zero', function () {
    $post = scorablePost(['published_at' => now()->subHour()]);

    expect(app(ForYouScorer::class)->score($post))->toBe(0.0);
});

test('an unpublished post scores zero', function () {
    $post = scorablePost(['likes_count' => 50, 'published_at' => null]);

    expect(app(ForYouScorer::class)->score($post))->toBe(0.0);
});

test('older posts decay below newer posts with identical engagement', function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $fresh = scorablePost(['likes_count' => 10, 'published_at' => now()->subHour()]);
    $stale = scorablePost(['likes_count' => 10, 'published_at' => now()->subHours(48)]);

    $scorer = app(ForYouScorer::class);

    expect($scorer->score($fresh))->toBeGreaterThan($scorer->score($stale))
        ->and($scorer->score($stale))->toBeGreaterThan(0);
});
