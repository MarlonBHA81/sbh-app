<?php

use App\Models\ActivityLog;
use App\Support\Activity;

test('a successful login is recorded in the audit log', function () {
    $user = userWithProfile(userAttributes: ['password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret-pass',
    ])->assertOk();

    $log = ActivityLog::query()->where('action', 'auth.login')->where('user_id', $user->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->ip)->not->toBeNull();
});

test('a data export is recorded in the audit log', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/me/export')->assertOk();

    expect(ActivityLog::query()->where('action', 'account.exported')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('issuing a bearer token is recorded in the audit log', function () {
    $user = userWithProfile(userAttributes: ['password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-pass',
        'device_name' => 'iPhone',
    ])->assertCreated();

    expect(ActivityLog::query()->where('action', 'token.issued')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('the activity helper captures request context', function () {
    $user = userWithProfile();

    $log = Activity::log('test.action', actor: $user, meta: ['k' => 'v']);

    expect($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('test.action')
        ->and($log->meta)->toBe(['k' => 'v']);
});
