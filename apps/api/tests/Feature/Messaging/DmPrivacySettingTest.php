<?php

test('dm_privacy defaults to everyone and is exposed to the owner', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('active_profile.dm_privacy', 'everyone');
});

test('the owner can update dm_privacy via the profile endpoint', function () {
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $this->actingAs($user)->patchJson("/api/v1/me/profiles/{$profile->ulid}", [
        'dm_privacy' => 'followers',
    ])->assertOk()
        ->assertJsonPath('data.dm_privacy', 'followers');

    expect($profile->fresh()->dm_privacy)->toBe('followers');
});

test('dm_privacy only accepts the allowed values', function () {
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $this->actingAs($user)->patchJson("/api/v1/me/profiles/{$profile->ulid}", [
        'dm_privacy' => 'sometimes',
    ])->assertJsonValidationErrors('dm_privacy');
});

test('dm_privacy is not exposed to other viewers', function () {
    $owner = userWithProfile();
    $viewer = userWithProfile();
    $owner->personalProfile->update(['dm_privacy' => 'no_one']);

    $this->actingAs($viewer)->getJson("/api/v1/profiles/{$owner->personalProfile->handle}")
        ->assertOk()
        ->assertJsonMissingPath('data.dm_privacy');
});
