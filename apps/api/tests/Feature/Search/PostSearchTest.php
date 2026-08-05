<?php

use App\Models\Post;
use App\Services\SafetyService;

test('post search matches body substrings, newest first, and paginates by cursor', function () {
    $me = userWithProfile();
    $author = userWithProfile();

    foreach (range(1, 25) as $i) {
        Post::factory()->create([
            'profile_id' => $author->personalProfile->id,
            'body' => "Unicorn sighting number {$i}",
            'published_at' => now()->subMinutes($i),
        ]);
    }
    Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'body' => 'Nothing relevant here',
    ]);

    $first = $this->actingAs($me)->getJson('/api/v1/search/posts?q=unicorn')->assertOk();
    $first->assertJsonCount(20, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->actingAs($me)->getJson('/api/v1/search/posts?q=unicorn&cursor='.$cursor)->assertOk();
    $second->assertJsonCount(5, 'data');

    $firstUlids = collect($first->json('data'))->pluck('ulid');
    $secondUlids = collect($second->json('data'))->pluck('ulid');
    expect($firstUlids->intersect($secondUlids))->toBeEmpty();
});

test('post search is case insensitive', function () {
    $me = userWithProfile();
    $author = userWithProfile();
    $post = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'body' => 'The QUICK brown fox',
    ]);

    $res = $this->actingAs($me)->getJson('/api/v1/search/posts?q=quick')->assertOk();
    expect(collect($res->json('data'))->pluck('ulid'))->toContain($post->ulid);
});

test('post search hides drafts, followers-only, private-profile and blocked-author posts', function () {
    $me = userWithProfile();
    $author = userWithProfile();
    $private = userWithProfile(['is_private' => true]);
    $blocker = userWithProfile();

    $visible = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'body' => 'keyword public']);
    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id, 'body' => 'keyword draft']);
    $followersOnly = Post::factory()->followersOnly()->create(['profile_id' => $author->personalProfile->id, 'body' => 'keyword followers']);
    $privatePost = Post::factory()->create(['profile_id' => $private->personalProfile->id, 'body' => 'keyword private']);
    $blockedPost = Post::factory()->create(['profile_id' => $blocker->personalProfile->id, 'body' => 'keyword blocked']);

    app(SafetyService::class)->block($blocker->personalProfile, $me->personalProfile);

    $res = $this->actingAs($me)->getJson('/api/v1/search/posts?q=keyword')->assertOk();
    $ulids = collect($res->json('data'))->pluck('ulid');

    expect($ulids)->toContain($visible->ulid)
        ->not->toContain($draft->ulid)
        ->not->toContain($followersOnly->ulid)
        ->not->toContain($privatePost->ulid)
        ->not->toContain($blockedPost->ulid);
});

test('post search enforces a minimum query length', function () {
    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/search/posts?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});
