<?php

use App\Models\Follow;
use App\Models\Post;

test('following feed contains posts from accepted follows and own posts only', function () {
    $viewer = userWithProfile();
    $followed = userWithProfile();
    $pending = userWithProfile();
    $stranger = userWithProfile();

    acceptedFollow($viewer->personalProfile, $followed->personalProfile);
    Follow::factory()->pending()->create([
        'follower_profile_id' => $viewer->personalProfile->id,
        'followed_profile_id' => $pending->personalProfile->id,
    ]);

    $followedPost = Post::factory()->create(['profile_id' => $followed->personalProfile->id]);
    $ownPost = Post::factory()->create(['profile_id' => $viewer->personalProfile->id]);
    $pendingPost = Post::factory()->create(['profile_id' => $pending->personalProfile->id]);
    $strangerPost = Post::factory()->create(['profile_id' => $stranger->personalProfile->id]);

    $response = $this->actingAs($viewer)->getJson('/api/v1/feeds/following')->assertOk();

    $ulids = collect($response->json('data'))->pluck('ulid');

    expect($ulids)->toContain($followedPost->ulid)
        ->toContain($ownPost->ulid)
        ->not->toContain($pendingPost->ulid)
        ->not->toContain($strangerPost->ulid);
});

test('following feed includes followers-only and private-profile posts of accepted follows', function () {
    $viewer = userWithProfile();
    $followed = userWithProfile(['is_private' => true]);

    acceptedFollow($viewer->personalProfile, $followed->personalProfile);

    $followersOnly = Post::factory()->followersOnly()->create(['profile_id' => $followed->personalProfile->id]);
    $public = Post::factory()->create(['profile_id' => $followed->personalProfile->id]);

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/following')->json('data'))->pluck('ulid');

    expect($ulids)->toContain($followersOnly->ulid)->toContain($public->ulid);
});

test('following feed excludes drafts and scheduled posts', function () {
    $viewer = userWithProfile();
    $followed = userWithProfile();

    acceptedFollow($viewer->personalProfile, $followed->personalProfile);

    Post::factory()->draft()->create(['profile_id' => $followed->personalProfile->id]);
    Post::factory()->scheduled()->create(['profile_id' => $followed->personalProfile->id]);
    Post::factory()->draft()->create(['profile_id' => $viewer->personalProfile->id]);

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/following')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('following feed orders by published_at desc and paginates with a cursor', function () {
    $viewer = userWithProfile();
    $followed = userWithProfile();

    acceptedFollow($viewer->personalProfile, $followed->personalProfile);

    foreach (range(1, 25) as $i) {
        Post::factory()->create([
            'profile_id' => $followed->personalProfile->id,
            'published_at' => now()->subMinutes($i),
        ]);
    }

    $first = $this->actingAs($viewer)->getJson('/api/v1/feeds/following')->assertOk();

    $first->assertJsonCount(20, 'data');

    $published = collect($first->json('data'))->pluck('published_at');
    expect($published->values()->all())->toBe($published->sortDesc()->values()->all());

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->actingAs($viewer)->getJson('/api/v1/feeds/following?cursor='.$cursor)->assertOk();

    $second->assertJsonCount(5, 'data');

    $firstUlids = collect($first->json('data'))->pluck('ulid');
    $secondUlids = collect($second->json('data'))->pluck('ulid');

    expect($firstUlids->intersect($secondUlids))->toBeEmpty();
});

test('following feed requires authentication', function () {
    $this->getJson('/api/v1/feeds/following')->assertUnauthorized();
});
