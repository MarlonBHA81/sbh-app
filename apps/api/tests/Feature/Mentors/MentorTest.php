<?php

use App\Models\Profile;

test('the mentors list returns only opted-in mentors', function () {
    $user = userWithProfile();

    Profile::factory()->create(['is_mentor' => true, 'name' => 'Mentor One', 'is_private' => false]);
    Profile::factory()->create(['is_mentor' => false, 'name' => 'Not a mentor', 'is_private' => false]);

    $this->actingAs($user)
        ->getJson('/api/v1/mentors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.profile.name', 'Mentor One')
        ->assertJsonPath('data.0.profile.is_mentor', true);
});

test('mentors in the same industry rank first', function () {
    $user = userWithProfile(['category' => 'Retail']);

    Profile::factory()->create([
        'is_mentor' => true, 'is_private' => false, 'category' => 'Technology', 'name' => 'Tech mentor',
    ]);
    Profile::factory()->create([
        'is_mentor' => true, 'is_private' => false, 'category' => 'Retail', 'name' => 'Retail mentor',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/mentors')
        ->assertOk()
        ->assertJsonPath('data.0.profile.name', 'Retail mentor')
        ->assertJsonPath('data.0.reason', 'Mentors in your industry');
});

test('the viewer is never listed as their own mentor', function () {
    $user = userWithProfile();
    $user->profiles()->first()->update(['is_mentor' => true]);

    $this->actingAs($user)
        ->getJson('/api/v1/mentors')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a member can opt in as a mentor via profile update', function () {
    $user = userWithProfile();
    $profile = $user->profiles()->first();

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$profile->ulid}", ['is_mentor' => true])
        ->assertOk()
        ->assertJsonPath('data.is_mentor', true);

    expect($profile->fresh()->is_mentor)->toBeTrue();
});

test('the mentors list requires authentication', function () {
    $this->getJson('/api/v1/mentors')->assertUnauthorized();
});
