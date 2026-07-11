<?php

use App\Models\Post;

// 0.09 degrees of latitude is roughly 10 km; 0.45 degrees roughly 50 km.
const NEARBY_LAT = 0.0;
const NEARBY_LNG = 10.0;

test('nearby feed validates coordinates and radius', function () {
    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/nearby')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng']);

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/nearby?lat=95&lng=10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat']);

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/nearby?lat=0&lng=10&radius_km=200')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['radius_km']);
});

test('nearby feed includes posts within the radius and excludes those beyond it', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    $near = Post::factory()->located(NEARBY_LAT + 0.09, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);
    $far = Post::factory()->located(NEARBY_LAT + 0.45, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);
    $unlocated = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $ulids = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/feeds/nearby?lat='.NEARBY_LAT.'&lng='.NEARBY_LNG.'&radius_km=25')
            ->assertOk()
            ->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($near->ulid)
        ->not->toContain($far->ulid)
        ->not->toContain($unlocated->ulid);
});

test('nearby feed default radius of 25km applies when none given', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    $near = Post::factory()->located(NEARBY_LAT + 0.09, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);
    $far = Post::factory()->located(NEARBY_LAT + 0.45, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);

    $ulids = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/feeds/nearby?lat='.NEARBY_LAT.'&lng='.NEARBY_LNG)
            ->assertOk()
            ->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($near->ulid)->not->toContain($far->ulid);

    // A wider radius picks the far post up again.
    $ulids = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/feeds/nearby?lat='.NEARBY_LAT.'&lng='.NEARBY_LNG.'&radius_km=100')
            ->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($near->ulid)->toContain($far->ulid);
});

test('nearby feed only shows public posts of public profiles', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();
    $private = userWithProfile(['is_private' => true]);

    $followersOnly = Post::factory()->followersOnly()->located(NEARBY_LAT, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);
    $privatePost = Post::factory()->located(NEARBY_LAT, NEARBY_LNG)->create([
        'profile_id' => $private->personalProfile->id,
    ]);
    $draft = Post::factory()->draft()->located(NEARBY_LAT, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);
    $visible = Post::factory()->located(NEARBY_LAT, NEARBY_LNG)->create([
        'profile_id' => $author->personalProfile->id,
    ]);

    $ulids = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/feeds/nearby?lat='.NEARBY_LAT.'&lng='.NEARBY_LNG)
            ->assertOk()
            ->json('data')
    )->pluck('ulid');

    expect($ulids)->toContain($visible->ulid)
        ->not->toContain($followersOnly->ulid)
        ->not->toContain($privatePost->ulid)
        ->not->toContain($draft->ulid);
});
