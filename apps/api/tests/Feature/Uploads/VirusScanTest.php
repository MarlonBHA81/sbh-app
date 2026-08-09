<?php

use App\Contracts\VirusScanner;
use App\Jobs\ProcessAudioUpload;
use App\Jobs\ProcessVideoUpload;
use App\Models\Media;
use App\Services\MediaService;
use App\Services\Security\NullVirusScanner;
use App\Services\Security\ScanResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeVirusScanner;

test('the rejects policy: infected always blocks, clean/disabled never', function () {
    $scanner = new NullVirusScanner;

    expect($scanner->rejects(ScanResult::infected('X')))->toBeTrue()
        ->and($scanner->rejects(ScanResult::clean()))->toBeFalse()
        ->and($scanner->rejects(ScanResult::disabled()))->toBeFalse();
});

test('an unavailable scanner blocks only when fail-closed is configured', function () {
    $scanner = new NullVirusScanner;

    config()->set('services.clamav.fail_closed', false);
    expect($scanner->rejects(ScanResult::unavailable('unreachable')))->toBeFalse();

    config()->set('services.clamav.fail_closed', true);
    expect($scanner->rejects(ScanResult::unavailable('unreachable')))->toBeTrue();
});

test('an infected image upload is rejected before it is stored', function () {
    Storage::fake('public');
    app()->instance(VirusScanner::class, FakeVirusScanner::infected());
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Media::count())->toBe(0);
});

test('a clean image upload passes the scan and is stored', function () {
    Storage::fake('public');
    app()->instance(VirusScanner::class, FakeVirusScanner::clean());
    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(Media::count())->toBe(1);
});

test('storeAvatar throws when the file is infected', function () {
    Storage::fake('public');
    $service = new MediaService(FakeVirusScanner::infected());
    $user = userWithProfile();

    $service->storeAvatar($user->personalProfile, UploadedFile::fake()->image('a.jpg'));
})->throws(ValidationException::class);

test('the video job quarantines an infected upload and deletes the file', function () {
    Storage::fake('public');
    $user = userWithProfile();
    Storage::disk('public')->put('media/'.$user->personalProfile->ulid.'/x.mp4', 'FAKEVIDEO');

    $media = Media::create([
        'profile_id' => $user->personalProfile->id,
        'type' => Media::TYPE_VIDEO,
        'disk' => 'public',
        'path' => 'media/'.$user->personalProfile->ulid.'/x.mp4',
        'mime' => 'video/mp4',
        'size_bytes' => 9,
        'status' => Media::STATUS_PROCESSING,
    ]);

    (new ProcessVideoUpload($media))->handle(app(MediaService::class), FakeVirusScanner::infected());

    expect($media->fresh()->status)->toBe(Media::STATUS_INFECTED);
    Storage::disk('public')->assertMissing($media->path);
});

test('the audio job quarantines an infected upload', function () {
    Storage::fake('public');
    $user = userWithProfile();
    Storage::disk('public')->put('media/'.$user->personalProfile->ulid.'/x.mp3', 'FAKEAUDIO');

    $media = Media::create([
        'profile_id' => $user->personalProfile->id,
        'type' => Media::TYPE_AUDIO,
        'disk' => 'public',
        'path' => 'media/'.$user->personalProfile->ulid.'/x.mp3',
        'mime' => 'audio/mpeg',
        'size_bytes' => 9,
        'status' => Media::STATUS_PROCESSING,
    ]);

    (new ProcessAudioUpload($media))->handle(FakeVirusScanner::infected());

    expect($media->fresh()->status)->toBe(Media::STATUS_INFECTED);
    Storage::disk('public')->assertMissing($media->path);
});

test('the audio job proceeds normally for a clean upload', function () {
    Storage::fake('public');
    $user = userWithProfile();
    Storage::disk('public')->put('media/'.$user->personalProfile->ulid.'/ok.mp3', 'FAKEAUDIO');

    $media = Media::create([
        'profile_id' => $user->personalProfile->id,
        'type' => Media::TYPE_AUDIO,
        'disk' => 'public',
        'path' => 'media/'.$user->personalProfile->ulid.'/ok.mp3',
        'mime' => 'audio/mpeg',
        'size_bytes' => 9,
        'status' => Media::STATUS_PROCESSING,
    ]);

    (new ProcessAudioUpload($media))->handle(FakeVirusScanner::clean());

    expect($media->fresh()->status)->toBe(Media::STATUS_READY);
    Storage::disk('public')->assertExists($media->path);
});

test('the container binds the no-op scanner when clamav is disabled', function () {
    config()->set('services.clamav.enabled', false);

    expect(app(VirusScanner::class))->toBeInstanceOf(NullVirusScanner::class);
});
