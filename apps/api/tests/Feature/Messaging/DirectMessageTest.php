<?php

use App\Models\Conversation;
use App\Services\SafetyService;
use Illuminate\Support\Str;

test('a dm is created between two profiles', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    $this->actingAs($me)
        ->postJson('/api/v1/conversations', [
            'kind' => 'dm',
            'profile_ulid' => $them->personalProfile->ulid,
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'dm')
        ->assertJsonPath('data.title', null);

    expect(Conversation::count())->toBe(1)
        ->and(Conversation::first()->participants()->count())->toBe(2);
});

test('creating a dm is idempotent (find-or-create returns the same conversation)', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    $first = $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertCreated()->json('data.ulid');

    $second = $this->actingAs($them)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $me->personalProfile->ulid,
    ])->assertCreated()->json('data.ulid');

    expect($second)->toBe($first)
        ->and(Conversation::count())->toBe(1);
});

test('leaving then re-opening a dm rejoins the existing conversation', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    $ulid = $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->json('data.ulid');

    $this->actingAs($me)
        ->deleteJson("/api/v1/conversations/{$ulid}/participants/{$me->personalProfile->ulid}")
        ->assertNoContent();

    $again = $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertCreated()->json('data.ulid');

    expect($again)->toBe($ulid)
        ->and(Conversation::count())->toBe(1);

    $participant = Conversation::first()->participants()
        ->where('profile_id', $me->personalProfile->id)->first();
    expect($participant->left_at)->toBeNull();
});

test('you cannot dm yourself', function () {
    $me = userWithProfile();

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $me->personalProfile->ulid,
    ])->assertUnprocessable();
});

test('dm_privacy everyone allows anyone to open a dm', function () {
    $me = userWithProfile();
    $them = userWithProfile();
    $them->personalProfile->update(['dm_privacy' => 'everyone']);

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertCreated();
});

test('dm_privacy no_one blocks dm creation with 403', function () {
    $me = userWithProfile();
    $them = userWithProfile();
    $them->personalProfile->update(['dm_privacy' => 'no_one']);

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertForbidden();

    expect(Conversation::count())->toBe(0);
});

test('dm_privacy followers requires the target to follow the creator', function () {
    $me = userWithProfile();
    $them = userWithProfile();
    $them->personalProfile->update(['dm_privacy' => 'followers']);

    // Not yet followed by the target -> forbidden.
    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertForbidden();

    // Target follows the creator -> allowed.
    acceptedFollow($them->personalProfile, $me->personalProfile);

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertCreated();
});

test('a creator who blocked the target gets 403 when opening a dm', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    app(SafetyService::class)->block($me->personalProfile, $them->personalProfile);

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertForbidden();
});

test('a creator blocked by the target gets 404 when opening a dm', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    app(SafetyService::class)->block($them->personalProfile, $me->personalProfile);

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->assertNotFound();
});

test('opening a dm with an unknown profile returns 404', function () {
    $me = userWithProfile();

    $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => (string) Str::ulid(),
    ])->assertNotFound();
});

test('the conversation list is ordered by recent activity', function () {
    $me = userWithProfile();
    $a = userWithProfile();
    $b = userWithProfile();

    $convA = $this->actingAs($me)->postJson('/api/v1/conversations', ['kind' => 'dm', 'profile_ulid' => $a->personalProfile->ulid])->json('data.ulid');
    $convB = $this->actingAs($me)->postJson('/api/v1/conversations', ['kind' => 'dm', 'profile_ulid' => $b->personalProfile->ulid])->json('data.ulid');

    // Activity in conversation A moves it to the top.
    $this->actingAs($me)->postJson("/api/v1/conversations/{$convA}/messages", ['body' => 'hi'])->assertCreated();

    $this->actingAs($me)->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.ulid', $convA)
        ->assertJsonPath('data.1.ulid', $convB);
});
