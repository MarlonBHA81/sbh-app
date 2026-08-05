<?php

use App\Jobs\PublishScheduledPost;
use App\Models\Post;
use App\Services\Posts\PostService;
use Illuminate\Support\Carbon;

test('a draft can be created and later published', function () {
    $user = userWithProfile();

    $ulid = $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'wip', 'status' => 'draft'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null)
        ->json('data.ulid');

    expect($user->personalProfile->refresh()->posts_count)->toBe(0);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$ulid}", ['status' => 'published'])
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($user->personalProfile->refresh()->posts_count)->toBe(1)
        ->and(Post::first()->published_at)->not->toBeNull();
});

test('scheduling requires a future scheduled_at', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'late',
            'status' => 'scheduled',
            'scheduled_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');
});

test('scheduled_at is rejected unless the post is being scheduled', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'now',
            'status' => 'published',
            'scheduled_at' => now()->addHour()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');
});

test('scheduled_at is interpreted in the user timezone and stored in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-11 08:00:00', 'UTC'));

    // Users default to Africa/Johannesburg (SAST, UTC+2).
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'later today',
            'status' => 'scheduled',
            'scheduled_at' => '2026-07-11 14:00:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'scheduled');

    expect(Post::first()->scheduled_at->format('Y-m-d H:i:s'))->toBe('2026-07-11 12:00:00');
});

test('posts:publish-due publishes due scheduled posts only', function () {
    $due = Post::factory()->scheduled(now()->subMinutes(5))->create();
    $future = Post::factory()->scheduled(now()->addHour())->create();

    $this->artisan('posts:publish-due')->assertSuccessful();

    expect($due->refresh()->status)->toBe(Post::STATUS_PUBLISHED)
        ->and($due->published_at)->not->toBeNull()
        ->and($due->profile->refresh()->posts_count)->toBe(1)
        ->and($future->refresh()->status)->toBe(Post::STATUS_SCHEDULED)
        ->and($future->published_at)->toBeNull();
});

test('the publish job publishes a scheduled repost and bumps parent counters', function () {
    $parent = Post::factory()->create();
    $repost = Post::factory()->repostOf($parent)->scheduled(now()->subMinute())->create();

    (new PublishScheduledPost($repost))->handle(app(PostService::class));

    expect($repost->refresh()->status)->toBe(Post::STATUS_PUBLISHED)
        ->and($parent->refresh()->reposts_count)->toBe(1);
});
