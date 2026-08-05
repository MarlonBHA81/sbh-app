<?php

use App\Models\Post;
use App\Models\PostStatsDaily;

function statToday(Post $post, string $metric): int
{
    $row = PostStatsDaily::query()
        ->where('post_id', $post->id)
        ->where('date', now()->utc()->toDateString())
        ->first();

    return (int) ($row->{$metric} ?? 0);
}

test('a like bumps the daily likes stat', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    expect(statToday($post, 'likes'))->toBe(1);
});

test('a comment bumps the daily comments stat', function () {
    $author = userWithProfile();
    $commenter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'Nice'])
        ->assertCreated();

    expect(statToday($post, 'comments'))->toBe(1);
});

test('a vote bumps the daily votes stat on the post', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 1])->assertOk();

    expect(statToday($post, 'votes'))->toBe(1);
});

test('publishing a repost bumps the parents daily reposts stat', function () {
    $author = userWithProfile();
    $reposter = userWithProfile();
    $parent = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($reposter)->postJson('/api/v1/posts', [
        'type' => Post::TYPE_REPOST,
        'parent_post_id' => $parent->ulid,
    ])->assertCreated();

    expect(statToday($parent, 'reposts'))->toBe(1);
});

test('revealing a hidden post bumps the daily views stat', function () {
    $author = userWithProfile();
    $viewer = userWithProfile();
    $post = Post::factory()->secret()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($viewer)->postJson("/api/v1/posts/{$post->ulid}/reveal")->assertOk();

    expect(statToday($post, 'views'))->toBe(1);
});

test('removing a like does not decrement the daily stat', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();
    $this->actingAs($liker)->deleteJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    expect(statToday($post, 'likes'))->toBe(1);
});
