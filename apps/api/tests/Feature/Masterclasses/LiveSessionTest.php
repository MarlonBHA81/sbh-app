<?php

use App\Models\Masterclass;
use App\Models\MasterclassLiveSession;
use Illuminate\Support\Facades\Http;

function liveRoom(): Masterclass
{
    return Masterclass::create([
        'title' => 'Live growth room',
        'description' => 'A live room.',
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(3),
        'is_published' => true,
    ]);
}

/** Enable the Mux driver with fake credentials + fake the Mux API. */
function useMux(): void
{
    config()->set('streaming.driver', 'mux');
    config()->set('streaming.mux.token_id', 'test-id');
    config()->set('streaming.mux.token_secret', 'test-secret');
    config()->set('streaming.mux.webhook_secret', null);
    config()->set('streaming.mux.api_url', 'https://api.mux.com');
    config()->set('streaming.mux.ingest_url', 'rtmps://global-live.mux.com:443/app');
    config()->set('streaming.mux.playback_base', 'https://stream.mux.com');

    Http::fake([
        'api.mux.com/video/v1/live-streams' => Http::response([
            'data' => [
                'id' => 'stream_abc',
                'stream_key' => 'super-secret-key',
                'playback_ids' => [['id' => 'play_xyz', 'policy' => 'public']],
            ],
        ], 201),
        'api.mux.com/video/v1/live-streams/*' => Http::response('', 204),
    ]);
}

test('an admin host creates a live stream and gets the ingest credentials', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();

    $this->actingAs($admin)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertCreated()
        ->assertJsonPath('data.ingest_url', 'rtmps://global-live.mux.com:443/app')
        ->assertJsonPath('data.stream_key', 'super-secret-key')
        ->assertJsonPath('data.playback_url', 'https://stream.mux.com/play_xyz.m3u8')
        ->assertJsonPath('data.status', 'idle');

    expect(MasterclassLiveSession::where('masterclass_id', $room->id)->count())->toBe(1);
});

test('a non-admin cannot host a live stream', function () {
    useMux();
    $user = userWithProfile();
    $room = liveRoom();

    $this->actingAs($user)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertStatus(403);
});

test('creating is unavailable when no streaming provider is configured', function () {
    config()->set('streaming.driver', 'null');
    $admin = adminWithProfile();
    $room = liveRoom();

    $this->actingAs($admin)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertStatus(422);
});

test('an enrolled member sees the playback url but not the stream key', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();

    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();

    $member = userWithProfile();
    $room->participants()->attach($member->profiles()->first()->id, ['enrolled_at' => now()]);

    $res = $this->actingAs($member)
        ->getJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertOk()
        ->assertJsonPath('data.can_watch', true)
        ->assertJsonPath('data.is_host', false)
        ->assertJsonPath('data.session.playback_url', 'https://stream.mux.com/play_xyz.m3u8');

    expect($res->json('data.session.stream_key'))->toBeNull()
        ->and($res->json('data.session.ingest_url'))->toBeNull();
});

test('a non-enrolled viewer cannot get the playback url', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();
    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();

    $this->flushHeaders();
    $viewer = userWithProfile();
    $this->actingAs($viewer)
        ->getJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertOk()
        ->assertJsonPath('data.can_watch', false)
        ->assertJsonPath('data.session.playback_url', null);
});

test('the provider webhook flips the session to live', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();
    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();

    $this->flushHeaders();
    $this->postJson('/api/v1/streaming/webhook', [
        'type' => 'video.live_stream.active',
        'data' => ['id' => 'stream_abc'],
    ])->assertOk();

    $session = MasterclassLiveSession::where('masterclass_id', $room->id)->first();
    expect($session->status)->toBe(MasterclassLiveSession::STATUS_ACTIVE)
        ->and($session->started_at)->not->toBeNull();

    // Going idle flips it back.
    $this->postJson('/api/v1/streaming/webhook', [
        'type' => 'video.live_stream.idle',
        'data' => ['id' => 'stream_abc'],
    ])->assertOk();

    expect($session->fresh()->status)->toBe(MasterclassLiveSession::STATUS_IDLE);
});

test('a ready recording becomes a replay for members', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();
    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();

    // Mux says the recording asset is ready for this live stream.
    $this->flushHeaders();
    $this->postJson('/api/v1/streaming/webhook', [
        'type' => 'video.asset.ready',
        'data' => [
            'id' => 'asset_1',
            'live_stream_id' => 'stream_abc',
            'playback_ids' => [['id' => 'replay_pid', 'policy' => 'public']],
        ],
    ])->assertOk();

    $session = MasterclassLiveSession::where('masterclass_id', $room->id)->first();
    expect($session->recording_playback_url)->toBe('https://stream.mux.com/replay_pid.m3u8');

    // An enrolled member sees the replay.
    $member = userWithProfile();
    $room->participants()->attach($member->profiles()->first()->id, ['enrolled_at' => now()]);

    $this->actingAs($member)
        ->getJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertOk()
        ->assertJsonPath('data.replays.0.recording_url', 'https://stream.mux.com/replay_pid.m3u8');

    // A non-enrolled viewer does not.
    $this->flushHeaders();
    $viewer = userWithProfile();
    $this->actingAs($viewer)
        ->getJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertOk()
        ->assertJsonCount(0, 'data.replays');
});

test('an admin can end the live stream', function () {
    useMux();
    $admin = adminWithProfile();
    $room = liveRoom();
    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/masterclasses/{$room->ulid}/live")
        ->assertNoContent();

    $session = MasterclassLiveSession::where('masterclass_id', $room->id)->first();
    expect($session->status)->toBe(MasterclassLiveSession::STATUS_ENDED)
        ->and($session->ended_at)->not->toBeNull();

    // A fresh host request starts a new session, not the ended one.
    $this->actingAs($admin)->postJson("/api/v1/masterclasses/{$room->ulid}/live")->assertCreated();
    expect(MasterclassLiveSession::where('masterclass_id', $room->id)->count())->toBe(2);
});
