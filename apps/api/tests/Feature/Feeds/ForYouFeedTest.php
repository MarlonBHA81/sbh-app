<?php

use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicFollow;

test('for-you feed excludes stale, non-public and unpublished posts', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    $fresh = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'published_at' => now()->subHours(3),
    ]);
    $stale = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'published_at' => now()->subDays(8),
    ]);
    $followersOnly = Post::factory()->followersOnly()->create([
        'profile_id' => $author->personalProfile->id,
        'published_at' => now()->subHour(),
    ]);
    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id]);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'))
        ->pluck('ulid');

    expect($ulids)->toContain($fresh->ulid)
        ->not->toContain($stale->ulid)
        ->not->toContain($followersOnly->ulid)
        ->not->toContain($draft->ulid);
});

test('for-you feed orders posts by score descending', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    $low = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'score' => 0.5,
        'published_at' => now()->subHour(),
    ]);
    $high = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'score' => 9.9,
        'published_at' => now()->subHours(5),
    ]);
    $mid = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'score' => 3.3,
        'published_at' => now()->subMinutes(10),
    ]);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->json('data'))->pluck('ulid');

    expect($ulids->values()->all())->toBe([$high->ulid, $mid->ulid, $low->ulid]);
});

test('for-you feed excludes posts already seen in a previous request', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    Post::factory()->count(3)->create(['profile_id' => $author->personalProfile->id]);

    $first = $this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk();
    $first->assertJsonCount(3, 'data');

    // Same request again (no cursor): everything was marked seen.
    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/for-you')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // A post published after the first page is still served.
    $newPost = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->json('data'))->pluck('ulid');

    expect($ulids->all())->toBe([$newPost->ulid]);
});

test('the seen set does not leak between profiles', function () {
    $viewer = userWithProfile();
    $other = userWithProfile();
    $author = userWithProfile();

    Post::factory()->count(2)->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertJsonCount(2, 'data');

    $this->actingAs($other)->getJson('/api/v1/feeds/for-you')->assertJsonCount(2, 'data');
});

test('for-you feed includes posts from followed topics and followed profiles', function () {
    $viewer = userWithProfile();
    $topicAuthor = userWithProfile();
    $followedAuthor = userWithProfile();

    $topic = Topic::factory()->create();
    TopicFollow::create(['profile_id' => $viewer->personalProfile->id, 'topic_id' => $topic->id]);
    acceptedFollow($viewer->personalProfile, $followedAuthor->personalProfile);

    $topicPost = Post::factory()->create(['profile_id' => $topicAuthor->personalProfile->id]);
    $topicPost->topics()->attach($topic->id);

    $followedPost = Post::factory()->create(['profile_id' => $followedAuthor->personalProfile->id]);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->json('data'))->pluck('ulid');

    expect($ulids)->toContain($topicPost->ulid)->toContain($followedPost->ulid);
});

test('for-you feed hides public posts of private profiles from non-followers', function () {
    $viewer = userWithProfile();
    $private = userWithProfile(['is_private' => true]);

    $post = Post::factory()->create(['profile_id' => $private->personalProfile->id]);

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/for-you')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    acceptedFollow($viewer->personalProfile, $private->personalProfile);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->json('data'))->pluck('ulid');

    expect($ulids)->toContain($post->ulid);
});
