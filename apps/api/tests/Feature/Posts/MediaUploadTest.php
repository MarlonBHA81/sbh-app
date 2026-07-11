<?php

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

test('an uploaded image is converted to webp and scaled down with a thumbnail', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->image('photo.jpg', 2500, 1500)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image')
        ->assertJsonPath('data.width', 1920)
        ->assertJsonPath('data.height', 1152);

    $media = Media::first();

    expect($media->path)
        ->toStartWith('media/'.$user->personalProfile->ulid.'/')
        ->toEndWith('.webp')
        ->and($media->mime)->toBe('image/webp')
        ->and($media->profile_id)->toBe($user->personalProfile->id);

    Storage::disk('public')->assertExists($media->path);
    Storage::disk('public')->assertExists($media->thumb_path);

    // RIFF....WEBP magic bytes prove actual webp conversion.
    $stored = Storage::disk('public')->get($media->path);
    expect(substr($stored, 0, 4))->toBe('RIFF')
        ->and(substr($stored, 8, 4))->toBe('WEBP');

    $thumb = Image::decodeBinary(Storage::disk('public')->get($media->thumb_path));
    expect($thumb->width())->toBe(480);
});

test('small images are not upscaled', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->image('small.png', 800, 600)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.width', 800)
        ->assertJsonPath('data.height', 600);
});

test('non-image uploads are rejected', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Media::count())->toBe(0);
});

test('uploads larger than 10MB are rejected', function () {
    Storage::fake('public');
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('guests cannot upload media', function () {
    $this->postJson('/api/v1/media')->assertUnauthorized();
});
