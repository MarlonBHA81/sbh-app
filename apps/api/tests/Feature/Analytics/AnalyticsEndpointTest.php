<?php

use App\Models\Follow;
use App\Models\Post;
use App\Models\PostStatsDaily;

function statsRow(Post $post, string $date, array $metrics): PostStatsDaily
{
    return PostStatsDaily::query()->create(array_merge([
        'post_id' => $post->id,
        'date' => $date,
    ], $metrics));
}

test('the overview aggregates daily stats across my posts', function () {
    $user = userWithProfile();
    $postA = Post::factory()->create(['profile_id' => $user->personalProfile->id]);
    $postB = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    $today = now()->utc()->toDateString();
    $yesterday = now()->utc()->subDay()->toDateString();

    statsRow($postA, $today, ['views' => 10, 'likes' => 2, 'comments' => 1, 'reposts' => 1, 'votes' => 3]);
    statsRow($postB, $yesterday, ['views' => 5, 'likes' => 1, 'comments' => 2, 'reposts' => 0, 'votes' => 1]);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/overview')->assertOk();

    $response->assertJsonPath('days', 30)
        ->assertJsonPath('totals.views', 15)
        ->assertJsonPath('totals.likes', 3)
        ->assertJsonPath('totals.comments', 3)
        ->assertJsonPath('totals.reposts', 1)
        ->assertJsonPath('totals.votes', 4)
        ->assertJsonPath('totals.posts_published', 2);
});

test('the overview series is zero-filled across the whole window', function () {
    $user = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    statsRow($post, now()->utc()->toDateString(), ['views' => 7]);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/overview?days=7')->assertOk();

    $series = $response->json('series');

    expect($series)->toHaveCount(7);

    // Every day in the window is present, oldest first, and days without
    // activity are zeroed.
    expect($series[0]['date'])->toBe(now()->utc()->subDays(6)->toDateString())
        ->and($series[0]['views'])->toBe(0)
        ->and(end($series)['date'])->toBe(now()->utc()->toDateString())
        ->and(end($series)['views'])->toBe(7);
});

test('stats outside the window and other creators posts are excluded', function () {
    $user = userWithProfile();
    $other = userWithProfile();

    $mine = Post::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'published_at' => now()->subDays(20),
    ]);
    $theirs = Post::factory()->create(['profile_id' => $other->personalProfile->id]);

    statsRow($mine, now()->utc()->subDays(10)->toDateString(), ['views' => 100]);
    statsRow($theirs, now()->utc()->toDateString(), ['views' => 50]);

    $this->actingAs($user)->getJson('/api/v1/analytics/overview?days=7')
        ->assertOk()
        ->assertJsonPath('totals.views', 0);
});

test('an invalid days value falls back to thirty', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/analytics/overview?days=14')
        ->assertOk()
        ->assertJsonPath('days', 30);

    $this->actingAs($user)->getJson('/api/v1/analytics/overview?days=90')
        ->assertOk()
        ->assertJsonPath('days', 90);
});

test('followers gained counts accepted follows within the window', function () {
    $user = userWithProfile();

    foreach (range(1, 2) as $ignored) {
        acceptedFollow(userWithProfile()->personalProfile, $user->personalProfile);
    }

    // A stale follow outside the window.
    Follow::factory()->create([
        'follower_profile_id' => userWithProfile()->personalProfile->id,
        'followed_profile_id' => $user->personalProfile->id,
        'created_at' => now()->subDays(60),
    ]);

    $this->actingAs($user)->getJson('/api/v1/analytics/overview?days=30')
        ->assertOk()
        ->assertJsonPath('totals.followers_gained', 2);
});

test('analytics posts ranks my posts by period views with engagement rate', function () {
    $user = userWithProfile();

    $top = Post::factory()->create(['profile_id' => $user->personalProfile->id]);
    $low = Post::factory()->create(['profile_id' => $user->personalProfile->id]);
    $idle = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    $today = now()->utc()->toDateString();

    statsRow($top, $today, ['views' => 100, 'likes' => 10, 'comments' => 5, 'reposts' => 5]);
    statsRow($low, $today, ['views' => 10, 'likes' => 1, 'comments' => 0, 'reposts' => 0]);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/posts?days=30')->assertOk();

    $data = collect($response->json('data'));

    expect($data->pluck('ulid')->all())->toBe([$top->ulid, $low->ulid, $idle->ulid]);

    $topRow = $data->firstWhere('ulid', $top->ulid);

    expect($topRow['views'])->toBe(100)
        // (10 + 5 + 5) / 100 * 100 = 20%
        ->and($topRow['engagement_rate_pct'])->toEqual(20.0);

    // A post with zero views divides by max(views, 1).
    $idleRow = $data->firstWhere('ulid', $idle->ulid);
    expect($idleRow['views'])->toBe(0)
        ->and($idleRow['engagement_rate_pct'])->toEqual(0.0);
});

test('analytics posts excludes other creators posts', function () {
    $user = userWithProfile();
    $other = userWithProfile();

    Post::factory()->create(['profile_id' => $other->personalProfile->id]);
    $mine = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    $data = collect($this->actingAs($user)->getJson('/api/v1/analytics/posts')->assertOk()->json('data'));

    expect($data->pluck('ulid')->all())->toBe([$mine->ulid]);
});

test('analytics requires authentication', function () {
    $this->getJson('/api/v1/analytics/overview')->assertUnauthorized();
    $this->getJson('/api/v1/analytics/posts')->assertUnauthorized();
});
