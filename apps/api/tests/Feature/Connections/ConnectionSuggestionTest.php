<?php

use App\Models\Follow;

test('suggestions exclude self and already-followed profiles', function () {
    $me = userWithProfile(['handle' => 'me']);
    $followed = userWithProfile(['handle' => 'followed']);
    $stranger = userWithProfile(['handle' => 'stranger']);

    Follow::create([
        'follower_profile_id' => $me->personalProfile->id,
        'followed_profile_id' => $followed->personalProfile->id,
        'state' => Follow::STATE_ACCEPTED,
    ]);

    $res = $this->actingAs($me)->getJson('/api/v1/me/connections')->assertOk();

    $handles = collect($res->json('data'))->pluck('profile.handle')->all();
    expect($handles)->toContain('stranger');
    expect($handles)->not->toContain('me');
    expect($handles)->not->toContain('followed');
});

test('a same-industry profile is suggested with the industry reason', function () {
    $me = userWithProfile(['handle' => 'me', 'category' => 'Retail']);
    userWithProfile(['handle' => 'peer', 'category' => 'Retail']);
    userWithProfile(['handle' => 'other', 'category' => 'Technology']);

    $res = $this->actingAs($me)->getJson('/api/v1/me/connections')->assertOk();

    $peer = collect($res->json('data'))->firstWhere('profile.handle', 'peer');
    expect($peer)->not->toBeNull();
    expect($peer['reason'])->toBe('Same industry');
});

test('private profiles are not suggested', function () {
    $me = userWithProfile(['handle' => 'me']);
    userWithProfile(['handle' => 'hidden', 'is_private' => true]);

    $res = $this->actingAs($me)->getJson('/api/v1/me/connections')->assertOk();

    $handles = collect($res->json('data'))->pluck('profile.handle')->all();
    expect($handles)->not->toContain('hidden');
});

test('connections require authentication', function () {
    $this->getJson('/api/v1/me/connections')->assertUnauthorized();
});
