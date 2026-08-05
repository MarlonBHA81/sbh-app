<?php

use App\Models\Mute;
use App\Models\Post;

test('muting a profile creates a mute record', function () {
    $actor = userWithProfile(['handle' => 'muter']);
    userWithProfile(['handle' => 'noisy']);

    $this->actingAs($actor)
        ->postJson('/api/v1/profiles/noisy/mute')
        ->assertCreated()
        ->assertJsonPath('status', 'muted');

    expect(Mute::count())->toBe(1);
});

test('a profile cannot mute itself', function () {
    $actor = userWithProfile(['handle' => 'selfmute']);

    $this->actingAs($actor)
        ->postJson('/api/v1/profiles/selfmute/mute')
        ->assertUnprocessable();
});

test('muted authors are excluded from the following feed', function () {
    $viewer = userWithProfile(['handle' => 'mute_viewer']);
    $muted = userWithProfile(['handle' => 'mute_author']);

    acceptedFollow($viewer->personalProfile, $muted->personalProfile);
    $post = Post::factory()->create(['profile_id' => $muted->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/profiles/mute_author/mute');

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/following')->json('data'))->pluck('ulid');
    expect($ulids)->not->toContain($post->ulid);
});

test('a muted profile is still viewable directly', function () {
    $viewer = userWithProfile(['handle' => 'mv_viewer']);
    $muted = userWithProfile(['handle' => 'mv_author', 'bio' => 'still visible']);

    $post = Post::factory()->create(['profile_id' => $muted->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/profiles/mv_author/mute');

    // Profile page still renders in full.
    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/mv_author')
        ->assertOk()
        ->assertJsonPath('data.limited', false)
        ->assertJsonPath('data.bio', 'still visible');

    // Their posts listing is still accessible.
    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/profiles/mv_author/posts')->json('data'))->pluck('ulid');
    expect($ulids)->toContain($post->ulid);
});

test('unmuting restores the author to the feed', function () {
    $viewer = userWithProfile(['handle' => 'un_viewer']);
    $muted = userWithProfile(['handle' => 'un_author']);

    acceptedFollow($viewer->personalProfile, $muted->personalProfile);
    $post = Post::factory()->create(['profile_id' => $muted->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/profiles/un_author/mute');
    $this->actingAs($viewer)->deleteJson('/api/v1/profiles/un_author/mute')->assertNoContent();

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/following')->json('data'))->pluck('ulid');
    expect($ulids)->toContain($post->ulid);
});

test('me/mutes lists muted profiles', function () {
    $actor = userWithProfile(['handle' => 'mutes_owner']);
    userWithProfile(['handle' => 'muted_target']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/muted_target/mute');

    $this->actingAs($actor)
        ->getJson('/api/v1/me/mutes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'muted_target');
});
