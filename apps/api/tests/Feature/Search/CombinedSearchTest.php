<?php

use App\Models\Topic;
use App\Services\SafetyService;

test('combined search returns matching profiles and topics in the expected shape', function () {
    $me = userWithProfile(['handle' => 'me', 'name' => 'Quiet Owl']);
    userWithProfile(['handle' => 'traveler', 'name' => 'Trav Eler']);
    Topic::factory()->create(['slug' => 'travel', 'name' => 'Travel']);
    Topic::factory()->create(['slug' => 'cooking', 'name' => 'Cooking']);

    $res = $this->actingAs($me)->getJson('/api/v1/search?q=trav')->assertOk();

    $res->assertJsonStructure([
        'profiles' => [['ulid', 'handle', 'name']],
        'topics' => [['id', 'slug', 'name', 'icon', 'followers_count']],
    ]);

    expect(collect($res->json('profiles'))->pluck('handle'))->toContain('traveler');
    expect(collect($res->json('topics'))->pluck('slug'))
        ->toContain('travel')
        ->not->toContain('cooking');
});

test('combined search matches profile name substrings', function () {
    $me = userWithProfile(['handle' => 'me', 'name' => 'Quiet Owl']);
    userWithProfile(['handle' => 'xyz', 'name' => 'Captain Nemo']);

    $res = $this->actingAs($me)->getJson('/api/v1/search?q=nemo')->assertOk();

    expect(collect($res->json('profiles'))->pluck('handle'))->toContain('xyz');
});

test('combined search caps each bucket at six', function () {
    $me = userWithProfile(['handle' => 'me', 'name' => 'Quiet Owl']);
    foreach (range(1, 8) as $i) {
        userWithProfile(['handle' => 'match'.$i]);
        Topic::factory()->create(['slug' => 'match-topic-'.$i, 'name' => 'Match Topic '.$i]);
    }

    $res = $this->actingAs($me)->getJson('/api/v1/search?q=match')->assertOk();

    expect($res->json('profiles'))->toHaveCount(6);
    expect($res->json('topics'))->toHaveCount(6);
});

test('combined search excludes blocked profiles in either direction', function () {
    $me = userWithProfile(['handle' => 'me', 'name' => 'Quiet Owl']);
    $iBlocked = userWithProfile(['handle' => 'blockone']);
    $blockedMe = userWithProfile(['handle' => 'blocktwo']);

    app(SafetyService::class)->block($me->personalProfile, $iBlocked->personalProfile);
    app(SafetyService::class)->block($blockedMe->personalProfile, $me->personalProfile);

    $res = $this->actingAs($me)->getJson('/api/v1/search?q=block')->assertOk();

    $handles = collect($res->json('profiles'))->pluck('handle');
    expect($handles)->not->toContain('blockone')->not->toContain('blocktwo');
});

test('combined search requires a minimum query length', function () {
    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/search?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

test('combined search requires authentication', function () {
    $this->getJson('/api/v1/search?q=abc')->assertUnauthorized();
});
