<?php

use App\Models\Profile;

test('the X-Profile-Id header switches the active profile', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/v1/me', ['X-Profile-Id' => $business->ulid])
        ->assertOk()
        ->assertJsonPath('active_profile.ulid', $business->ulid)
        ->assertJsonPath('active_profile.kind', 'business');
});

test('using another users profile id is forbidden', function () {
    $user = userWithProfile();
    $other = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/me', ['X-Profile-Id' => $other->personalProfile->ulid])
        ->assertForbidden();
});

test('an unknown profile id is forbidden', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/me', ['X-Profile-Id' => '01JUNKJUNKJUNKJUNKJUNKJUNK'])
        ->assertForbidden();
});
