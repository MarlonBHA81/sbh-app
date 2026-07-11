<?php

use App\Models\Media;
use App\Models\UploadSession;
use Illuminate\Support\Facades\Storage;

/** Send a raw-body chunk PUT for the given session/index. */
function putChunk($test, $user, UploadSession $session, int $index, string $body)
{
    return $test->actingAs($user)->call(
        'PUT',
        "/api/v1/uploads/{$session->ulid}/chunks/{$index}",
        [], [], [],
        ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
        $body,
    );
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    // Tiny chunks so a 3-chunk fixture stays small.
    config(['media.chunk_size' => 4]);
});

test('initialising an upload session returns chunk metadata', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4',
        'mime' => 'video/mp4',
        'size_bytes' => 10,
        'type' => 'video',
    ])
        ->assertCreated()
        ->assertJsonPath('data.chunk_size', 4)
        ->assertJsonPath('data.total_chunks', 3);

    expect(UploadSession::count())->toBe(1);
});

test('upload init rejects disallowed mime types', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mov',
        'mime' => 'video/x-flv',
        'size_bytes' => 10,
        'type' => 'video',
    ])->assertUnprocessable()->assertJsonValidationErrors('mime');
});

test('upload init rejects oversized files', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'song.mp3',
        'mime' => 'audio/mpeg',
        'size_bytes' => 60 * 1024 * 1024, // > 50MB audio ceiling
        'type' => 'audio',
    ])->assertUnprocessable()->assertJsonValidationErrors('size_bytes');
});

test('chunks upload and re-uploading the same index is idempotent', function () {
    $user = userWithProfile();

    $session = $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);

    putChunk($this, $user, $model, 0, 'aaaa')->assertOk()->assertJsonPath('data.received_chunks', 1);

    // Re-put the same index -> still counted once.
    putChunk($this, $user, $model, 0, 'aaaa')->assertOk()->assertJsonPath('data.received_chunks', 1);

    expect($model->fresh()->received_chunks)->toBe(1);
});

test('completing an upload assembles the chunks byte-for-byte', function () {
    $user = userWithProfile();

    $session = $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);

    $parts = ['aaaa', 'bbbb', 'cc'];
    foreach ($parts as $i => $part) {
        putChunk($this, $user, $model, $i, $part)->assertOk();
    }

    $response = $this->actingAs($user)->postJson("/api/v1/uploads/{$model->ulid}/complete")
        ->assertCreated();

    $media = Media::firstWhere('ulid', $response->json('data.ulid'));
    $expected = implode('', $parts);

    $stored = Storage::disk('public')->get($media->path);
    expect($stored)->toBe($expected)
        ->and(hash('sha256', $stored))->toBe(hash('sha256', $expected))
        ->and($media->size_bytes)->toBe(strlen($expected));

    // Chunks are cleaned up and the session is marked done.
    Storage::disk('local')->assertMissing($model->chunkDirectory());
    expect($model->fresh()->status)->toBe(UploadSession::STATUS_DONE);
});

test('completing an incomplete upload fails validation', function () {
    $user = userWithProfile();

    $session = $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);
    putChunk($this, $user, $model, 0, 'aaaa');

    $this->actingAs($user)->postJson("/api/v1/uploads/{$model->ulid}/complete")
        ->assertUnprocessable();
});

test('without ffmpeg the completed media is ready with no thumbnail', function () {
    $user = userWithProfile();

    $session = $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 8, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);
    putChunk($this, $user, $model, 0, 'aaaa');
    putChunk($this, $user, $model, 1, 'bbbb');

    // Sync queue runs ProcessVideoUpload immediately; with no ffmpeg binary it
    // takes the graceful path.
    $this->actingAs($user)->postJson("/api/v1/uploads/{$model->ulid}/complete")
        ->assertCreated()
        ->assertJsonPath('data.status', Media::STATUS_READY)
        ->assertJsonPath('data.thumb_url', null);
});

test('a session owner can poll media status', function () {
    $user = userWithProfile();
    $media = Media::factory()->video()->processing()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->getJson("/api/v1/media/{$media->ulid}")
        ->assertOk()
        ->assertJsonPath('data.status', Media::STATUS_PROCESSING);

    $other = userWithProfile();
    $this->actingAs($other)->getJson("/api/v1/media/{$media->ulid}")->assertForbidden();
});

test('aborting an upload cleans up chunks', function () {
    $user = userWithProfile();

    $session = $this->actingAs($user)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);
    putChunk($this, $user, $model, 0, 'aaaa');

    $this->actingAs($user)->deleteJson("/api/v1/uploads/{$model->ulid}")->assertNoContent();

    Storage::disk('local')->assertMissing($model->chunkDirectory());
    expect($model->fresh()->status)->toBe(UploadSession::STATUS_ABORTED);
});

test('a non-owner cannot upload chunks to a session', function () {
    $owner = userWithProfile();

    $session = $this->actingAs($owner)->postJson('/api/v1/uploads', [
        'filename' => 'clip.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'type' => 'video',
    ])->json('data');

    $model = UploadSession::firstWhere('ulid', $session['ulid']);
    $intruder = userWithProfile();

    putChunk($this, $intruder, $model, 0, 'aaaa')->assertForbidden();
});

test('the prune command aborts stale sessions', function () {
    $user = userWithProfile();

    $stale = UploadSession::create([
        'profile_id' => $user->personalProfile->id,
        'filename' => 'old.mp4', 'mime' => 'video/mp4', 'media_type' => 'video',
        'size_bytes' => 10, 'total_chunks' => 3, 'status' => UploadSession::STATUS_PENDING,
    ]);
    $stale->forceFill(['created_at' => now()->subHours(30)])->save();
    Storage::disk('local')->put($stale->chunkDirectory().'/0', 'aaaa');

    $fresh = UploadSession::create([
        'profile_id' => $user->personalProfile->id,
        'filename' => 'new.mp4', 'mime' => 'video/mp4', 'media_type' => 'video',
        'size_bytes' => 10, 'total_chunks' => 3, 'status' => UploadSession::STATUS_PENDING,
    ]);

    $this->artisan('uploads:prune')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(UploadSession::STATUS_ABORTED)
        ->and($fresh->fresh()->status)->toBe(UploadSession::STATUS_PENDING);
    Storage::disk('local')->assertMissing($stale->chunkDirectory());
});

test('uploads require authentication', function () {
    $this->postJson('/api/v1/uploads', [])->assertUnauthorized();
});
