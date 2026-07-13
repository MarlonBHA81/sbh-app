<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can upload an avatar for their profile', function () {
    Storage::fake('public');
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $response = $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$profile->ulid}/avatar", [
            'image' => UploadedFile::fake()->image('me.jpg', 800, 800),
        ])
        ->assertOk();

    expect($response->json('data.avatar_url'))->not->toBeNull();
    Storage::disk('public')->assertExists("media/avatars/{$profile->ulid}.webp");
});

test('a user cannot upload an avatar for a profile they do not own', function () {
    Storage::fake('public');
    $owner = userWithProfile();
    $other = userWithProfile();

    $this->actingAs($other)
        ->post("/api/v1/me/profiles/{$owner->personalProfile->ulid}/avatar", [
            'image' => UploadedFile::fake()->image('x.jpg', 400, 400),
        ])
        ->assertForbidden();
});

test('non-image uploads are rejected', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson("/api/v1/me/profiles/{$user->personalProfile->ulid}/avatar", [
            'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

test('a business profile can upload a cover banner', function () {
    Storage::fake('public');
    $user = userWithProfile();
    $business = \App\Models\Profile::factory()->for($user)->business()->create();

    $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$business->ulid}/cover", [
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
        ])
        ->assertOk()
        ->assertJsonPath('data.cover_url', fn ($url) => $url !== null);

    Storage::disk('public')->assertExists("media/covers/{$business->ulid}.webp");
});

test('a personal profile cannot upload a cover banner', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$user->personalProfile->ulid}/cover", [
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
        ])
        ->assertStatus(422);
});

test('an avatar can be removed', function () {
    Storage::fake('public');
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $this->actingAs($user)->post("/api/v1/me/profiles/{$profile->ulid}/avatar", [
        'image' => UploadedFile::fake()->image('me.jpg', 400, 400),
    ])->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/v1/me/profiles/{$profile->ulid}/avatar")
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);

    Storage::disk('public')->assertMissing("media/avatars/{$profile->ulid}.webp");
});
