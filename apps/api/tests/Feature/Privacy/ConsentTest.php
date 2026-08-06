<?php

use App\Models\ActivityLog;
use App\Models\ConsentRecord;

test('an authenticated user can record a consent decision', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/consent', ['choice' => 'accepted'])
        ->assertCreated()
        ->assertJsonPath('data.choice', 'accepted')
        ->assertJsonPath('data.policy_version', (string) config('privacy.policy_version'));

    $record = ConsentRecord::query()->where('user_id', $user->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->choice)->toBe('accepted')
        ->and($record->ip)->not->toBeNull();

    // Recording consent also lands in the audit log.
    expect(ActivityLog::query()->where('action', 'consent.recorded')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('consent choice is validated', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/consent', ['choice' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('choice');
});

test('recording consent appends a new row each time and show returns the latest', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/me/consent', ['choice' => 'rejected'])->assertCreated();
    $this->actingAs($user)->postJson('/api/v1/me/consent', ['choice' => 'accepted'])->assertCreated();

    expect(ConsentRecord::query()->where('user_id', $user->id)->count())->toBe(2);

    $this->actingAs($user)
        ->getJson('/api/v1/me/consent')
        ->assertOk()
        ->assertJsonPath('data.choice', 'accepted');
});

test('consent endpoints require authentication', function () {
    $this->postJson('/api/v1/me/consent', ['choice' => 'accepted'])->assertUnauthorized();
    $this->getJson('/api/v1/me/consent')->assertUnauthorized();
});
