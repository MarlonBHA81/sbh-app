<?php

use App\Models\Post;
use App\Models\Topic;

test('topic feed includes posts attached to the topic and to its descendants', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    $tech = Topic::factory()->create(['slug' => 'technology', 'name' => 'Technology', 'path' => 'technology']);
    $ai = Topic::factory()->create([
        'slug' => 'ai', 'name' => 'AI', 'parent_id' => $tech->id, 'path' => 'technology/ai', 'depth' => 1,
    ]);
    $food = Topic::factory()->create(['slug' => 'food', 'name' => 'Food', 'path' => 'food']);

    $rootPost = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $rootPost->topics()->attach($tech->id);

    $childPost = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $childPost->topics()->attach($ai->id);

    $otherPost = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $otherPost->topics()->attach($food->id);

    $untaggedPost = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/feeds/topics/technology')->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($rootPost->ulid)
        ->toContain($childPost->ulid)
        ->not->toContain($otherPost->ulid)
        ->not->toContain($untaggedPost->ulid);

    // The child topic's feed does not include the parent's posts.
    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/topics/ai')->json('data'))->pluck('ulid');

    expect($ulids)->toContain($childPost->ulid)->not->toContain($rootPost->ulid);
});

test('topic feed only shows published public posts and respects private profiles', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();
    $private = userWithProfile(['is_private' => true]);

    $topic = Topic::factory()->create(['slug' => 'travel', 'name' => 'Travel', 'path' => 'travel']);

    $followersOnly = Post::factory()->followersOnly()->create(['profile_id' => $author->personalProfile->id]);
    $followersOnly->topics()->attach($topic->id);

    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id]);
    $draft->topics()->attach($topic->id);

    $privatePost = Post::factory()->create(['profile_id' => $private->personalProfile->id]);
    $privatePost->topics()->attach($topic->id);

    $visible = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $visible->topics()->attach($topic->id);

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/feeds/topics/travel')->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($visible->ulid)
        ->not->toContain($followersOnly->ulid)
        ->not->toContain($draft->ulid)
        ->not->toContain($privatePost->ulid);
});

test('topic feed 404s for an unknown slug', function () {
    $viewer = userWithProfile();

    $this->actingAs($viewer)->getJson('/api/v1/feeds/topics/nope')->assertNotFound();
});
