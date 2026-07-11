<?php

use App\Models\Profile;

test('me returns the user with profiles and the active personal profile', function () {
    $user = userWithProfile(['handle' => 'my_handle']);

    $this->actingAs($user)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('active_profile.handle', 'my_handle')
        ->assertJsonPath('active_profile.relationship', 'self')
        ->assertJsonCount(1, 'profiles');
});

test('me can be updated with locale, timezone and settings', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->patchJson('/api/v1/me', [
            'locale' => 'af',
            'timezone' => 'Africa/Johannesburg',
            'settings' => ['dark_mode' => true],
        ])
        ->assertOk()
        ->assertJsonPath('user.locale', 'af')
        ->assertJsonPath('user.settings.dark_mode', true);
});

test('a business profile can be created', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'Braai Spot',
            'category' => 'restaurant',
            'website' => 'https://braaispot.example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'business')
        ->assertJsonPath('data.category', 'restaurant');

    expect($user->businessProfiles()->count())->toBe(1);
});

test('creating a fourth business profile is rejected with 422', function () {
    $user = userWithProfile();

    Profile::factory()->business()->for($user)->count(3)->create();

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', ['name' => 'One Too Many'])
        ->assertUnprocessable();

    expect($user->businessProfiles()->count())->toBe(3);
});

test('a user can update their own profile including the handle', function () {
    $user = userWithProfile(['handle' => 'old_handle']);
    $profile = $user->personalProfile;

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$profile->ulid}", [
            'name' => 'New Name',
            'bio' => 'New bio',
            'handle' => 'New_Handle',
            'is_private' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.handle', 'new_handle')
        ->assertJsonPath('data.is_private', true);
});

test('updating another users profile is forbidden', function () {
    $user = userWithProfile();
    $other = userWithProfile();

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$other->personalProfile->ulid}", ['name' => 'Hijack'])
        ->assertForbidden();
});

test('updating a handle to one already taken is rejected', function () {
    userWithProfile(['handle' => 'taken_handle']);

    $user = userWithProfile(['handle' => 'mine']);

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$user->personalProfile->ulid}", ['handle' => 'taken_handle'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('handle');
});

test('a business profile can be soft deleted', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson("/api/v1/me/profiles/{$business->ulid}")
        ->assertNoContent();

    expect(Profile::withTrashed()->find($business->id)->trashed())->toBeTrue();
});

test('a personal profile cannot be deleted', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->deleteJson("/api/v1/me/profiles/{$user->personalProfile->ulid}")
        ->assertUnprocessable();
});

test('a public profile is fully visible to guests', function () {
    userWithProfile(['handle' => 'public_person', 'bio' => 'Hello world']);

    $this->getJson('/api/v1/profiles/public_person')
        ->assertOk()
        ->assertJsonPath('data.limited', false)
        ->assertJsonPath('data.bio', 'Hello world')
        ->assertJsonPath('data.relationship', 'none');
});

test('a private profile returns a limited payload to non-followers', function () {
    userWithProfile(['handle' => 'private_person', 'bio' => 'Secret', 'is_private' => true]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/private_person')
        ->assertOk()
        ->assertJsonPath('data.limited', true)
        ->assertJsonPath('data.is_private', true)
        ->assertJsonPath('data.relationship', 'none')
        ->assertJsonMissingPath('data.bio');
});

test('profile lookup by handle is case insensitive', function () {
    userWithProfile(['handle' => 'mixedcase']);

    $this->getJson('/api/v1/profiles/MixedCase')->assertOk();
});
