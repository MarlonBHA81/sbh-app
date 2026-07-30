<?php

use App\Models\Profile;
use Illuminate\Contracts\Filesystem\Filesystem;
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

    $path = $profile->fresh()->avatar_path;
    expect($path)->toStartWith("media/avatars/{$profile->ulid}-");
    Storage::disk('public')->assertExists($path);
});

test('replacing an avatar yields a new url and deletes the old file', function () {
    Storage::fake('public');
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $first = $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$profile->ulid}/avatar", [
            'image' => UploadedFile::fake()->image('a.jpg', 500, 500),
        ])->assertOk()->json('data.avatar_url');
    $firstPath = $profile->fresh()->avatar_path;

    $second = $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$profile->ulid}/avatar", [
            'image' => UploadedFile::fake()->image('b.jpg', 500, 500),
        ])->assertOk()->json('data.avatar_url');
    $secondPath = $profile->fresh()->avatar_path;

    // A distinct filename → a distinct URL, so the browser never shows the old
    // cached photo after an edit; the previous file is cleaned up.
    expect($secondPath)->not->toBe($firstPath)
        ->and($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('a failed disk write surfaces as an error instead of a silent broken image', function () {
    $user = userWithProfile();
    $profile = $user->personalProfile;

    // The public disk is configured with 'throw' => false, so a write to an
    // unwritable volume (e.g. a root-owned prod media volume php-fpm can't
    // touch) returns false. That must NOT be swallowed into a saved DB path
    // pointing at a file that never landed — otherwise the app serves a broken
    // image forever. Simulate the failure and assert the column stays null.
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(false);
    $disk->shouldReceive('delete')->andReturn(true);
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$profile->ulid}/avatar", [
            'image' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])
        ->assertStatus(500);

    expect($profile->fresh()->avatar_path)->toBeNull();
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
    $business = Profile::factory()->for($user)->business()->create();

    $this->actingAs($user)
        ->post("/api/v1/me/profiles/{$business->ulid}/cover", [
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
        ])
        ->assertOk()
        ->assertJsonPath('data.cover_url', fn ($url) => $url !== null);

    $path = $business->fresh()->cover_path;
    expect($path)->toStartWith("media/covers/{$business->ulid}-");
    Storage::disk('public')->assertExists($path);
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
    $path = $profile->fresh()->avatar_path;

    $this->actingAs($user)
        ->deleteJson("/api/v1/me/profiles/{$profile->ulid}/avatar")
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);

    Storage::disk('public')->assertMissing($path);
});
