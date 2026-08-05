<?php

use App\Models\Block;
use App\Models\Post;
use App\Models\Profile;
use App\Support\Geohash;

function eventPost(array $postAttrs, string $startsAt): Post
{
    $post = Post::factory()->create(array_merge([
        'type' => Post::TYPE_EVENT,
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => Post::STATUS_PUBLISHED,
        'published_at' => now(),
    ], $postAttrs));

    $post->event()->create([
        'title' => 'Event '.$post->id,
        'starts_at' => $startsAt,
    ]);

    return $post;
}

test('upcoming events are returned soonest first', function () {
    $viewer = userWithProfile();

    $later = eventPost([], now()->addDays(10)->toDateTimeString());
    $soon = eventPost([], now()->addDay()->toDateTimeString());
    $past = eventPost([], now()->subDay()->toDateTimeString());

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/events?filter=upcoming')->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids->toArray())->toBe([$soon->ulid, $later->ulid]);
    expect($ulids)->not->toContain($past->ulid);
});

test('past events are returned most recent first', function () {
    $viewer = userWithProfile();

    $old = eventPost([], now()->subDays(10)->toDateTimeString());
    $recent = eventPost([], now()->subDay()->toDateTimeString());
    $future = eventPost([], now()->addDay()->toDateTimeString());

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/events?filter=past')->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids->toArray())->toBe([$recent->ulid, $old->ulid]);
    expect($ulids)->not->toContain($future->ulid);
});

test('events default to the upcoming filter', function () {
    $viewer = userWithProfile();

    $future = eventPost([], now()->addDay()->toDateTimeString());
    eventPost([], now()->subDay()->toDateTimeString());

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/events')->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids->toArray())->toBe([$future->ulid]);
});

test('events can be filtered by geo radius', function () {
    $viewer = userWithProfile();

    // Cape Town.
    $near = eventPost([
        'lat' => -33.9249, 'lng' => 18.4241, 'geohash' => Geohash::encode(-33.9249, 18.4241),
    ], now()->addDay()->toDateTimeString());

    // Johannesburg (~1260 km away).
    $far = eventPost([
        'lat' => -26.2041, 'lng' => 28.0473, 'geohash' => Geohash::encode(-26.2041, 28.0473),
    ], now()->addDay()->toDateTimeString());

    $ulids = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/business/events?filter=upcoming&lat=-33.9249&lng=18.4241&radius_km=50')
            ->assertOk()->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($near->ulid)->not->toContain($far->ulid);
});

test('events exclude posts from blocked authors', function () {
    $viewer = userWithProfile();

    $blocked = Profile::factory()->create();
    Block::create([
        'blocker_profile_id' => $viewer->personalProfile->id,
        'blocked_profile_id' => $blocked->id,
    ]);

    $hidden = eventPost(['profile_id' => $blocked->id], now()->addDay()->toDateTimeString());
    $visible = eventPost([], now()->addDay()->toDateTimeString());

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/events?filter=upcoming')->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($visible->ulid)->not->toContain($hidden->ulid);
});

test('events exclude non-event and followers-only posts', function () {
    $viewer = userWithProfile();

    $event = eventPost([], now()->addDay()->toDateTimeString());
    $followersEvent = eventPost(['visibility' => Post::VISIBILITY_FOLLOWERS], now()->addDay()->toDateTimeString());
    Post::factory()->create(['type' => 'text', 'published_at' => now()]);

    $ulids = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/events?filter=upcoming')->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($event->ulid)
        ->not->toContain($followersEvent->ulid)
        ->toHaveCount(1);
});
