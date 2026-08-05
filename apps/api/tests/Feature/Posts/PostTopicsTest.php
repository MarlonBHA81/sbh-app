<?php

use App\Models\Post;
use App\Models\Topic;
use App\Support\Geohash;

test('a post can be created with up to three topics by id or slug', function () {
    $user = userWithProfile();

    $tech = Topic::factory()->create(['slug' => 'technology']);
    $ai = Topic::factory()->childOf($tech)->create(['slug' => 'ai']);
    $food = Topic::factory()->create(['slug' => 'food']);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'Tagged post',
            'topic_ids' => [$tech->id, 'ai', $food->slug],
        ])
        ->assertCreated();

    $topics = collect($response->json('data.topics'));

    expect($topics)->toHaveCount(3)
        ->and($topics->pluck('slug')->sort()->values()->all())->toBe(['ai', 'food', 'technology'])
        ->and($topics->first())->toHaveKeys(['id', 'slug', 'name', 'icon']);

    $post = Post::where('ulid', $response->json('data.ulid'))->firstOrFail();

    expect($post->topics)->toHaveCount(3)
        ->and($tech->refresh()->posts_count)->toBe(1)
        ->and($ai->refresh()->posts_count)->toBe(1)
        ->and($food->refresh()->posts_count)->toBe(1);
});

test('topic_ids rejects more than three topics and unknown topics', function () {
    $user = userWithProfile();

    $topics = Topic::factory()->count(4)->create();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'too many',
            'topic_ids' => $topics->pluck('id')->all(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('topic_ids');

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'unknown',
            'topic_ids' => ['does-not-exist'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('topic_ids.0');
});

test('topic posts_count is only bumped when the post is published', function () {
    $user = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'business']);

    $ulid = $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'wip',
            'status' => 'draft',
            'topic_ids' => ['business'],
        ])
        ->assertCreated()
        ->json('data.ulid');

    expect($topic->refresh()->posts_count)->toBe(0);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$ulid}", ['status' => 'published'])
        ->assertOk();

    expect($topic->refresh()->posts_count)->toBe(1);
});

test('publishing a due scheduled post bumps topic posts_count', function () {
    $user = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'news']);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'later',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour()->setTimezone('Africa/Johannesburg')->toDateTimeString(),
            'topic_ids' => ['news'],
        ])
        ->assertCreated();

    expect($topic->refresh()->posts_count)->toBe(0);

    $this->travel(2)->hours();

    $this->artisan('posts:publish-due')->assertSuccessful();

    expect($topic->refresh()->posts_count)->toBe(1);
});

test('deleting a published post decrements topic posts_count', function () {
    $user = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'travel']);

    $ulid = $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'bye',
            'topic_ids' => ['travel'],
        ])
        ->json('data.ulid');

    expect($topic->refresh()->posts_count)->toBe(1);

    $this->actingAs($user)->deleteJson("/api/v1/posts/{$ulid}")->assertNoContent();

    expect($topic->refresh()->posts_count)->toBe(0);
});

test('duplicate topics are attached once', function () {
    $user = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'music']);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'dupes',
            'topic_ids' => [$topic->id, 'music'],
        ])
        ->assertCreated();

    expect($response->json('data.topics'))->toHaveCount(1)
        ->and($topic->refresh()->posts_count)->toBe(1);
});

test('a post created with coordinates gets a geohash', function () {
    $user = userWithProfile();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'checkin',
            'payload' => ['place_name' => 'Table Mountain'],
            'lat' => -33.9628,
            'lng' => 18.4098,
        ])
        ->assertCreated();

    $post = Post::where('ulid', $response->json('data.ulid'))->firstOrFail();

    expect($post->geohash)->toBe(Geohash::encode(-33.9628, 18.4098));
});
