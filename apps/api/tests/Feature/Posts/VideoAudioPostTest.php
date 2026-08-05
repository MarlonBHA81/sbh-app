<?php

use App\Models\Media;
use App\Models\Post;

test('a video post publishes with a ready video media item', function () {
    $user = userWithProfile();
    $media = Media::factory()->video()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'video',
        'body' => 'My clip caption',
        'media_ids' => [$media->ulid],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.body', 'My clip caption')
        ->assertJsonPath('data.media.0.type', 'video');
});

test('publishing a video with still-processing media is rejected', function () {
    $user = userWithProfile();
    $media = Media::factory()->video()->processing()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'video',
        'media_ids' => [$media->ulid],
        'status' => 'published',
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');

    expect(Post::count())->toBe(0);
});

test('a video draft may attach still-processing media', function () {
    $user = userWithProfile();
    $media = Media::factory()->video()->processing()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'video',
        'media_ids' => [$media->ulid],
        'status' => 'draft',
    ])->assertCreated()->assertJsonPath('data.status', 'draft');
});

test('a video post rejects non-video media', function () {
    $user = userWithProfile();
    $image = Media::factory()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'video',
        'media_ids' => [$image->ulid],
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');
});

test('an audio post carries an optional title payload', function () {
    $user = userWithProfile();
    $media = Media::factory()->audio()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'audio',
        'payload' => ['title' => 'Episode 1'],
        'media_ids' => [$media->ulid],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'audio')
        ->assertJsonPath('data.payload.title', 'Episode 1')
        ->assertJsonPath('data.media.0.type', 'audio');
});

test('an audio post rejects video media', function () {
    $user = userWithProfile();
    $media = Media::factory()->video()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'audio',
        'media_ids' => [$media->ulid],
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');
});
