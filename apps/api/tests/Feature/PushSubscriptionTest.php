<?php

use NotificationChannels\WebPush\PushSubscription;

test('a push subscription can be stored', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/me/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
        'keys' => ['p256dh' => 'the-p256dh-key', 'auth' => 'the-auth-token'],
    ])->assertCreated();

    expect(PushSubscription::where('endpoint', 'https://push.example.com/abc')->exists())->toBeTrue()
        ->and($user->pushSubscriptions()->count())->toBe(1);
});

test('storing a subscription validates the keys', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/me/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
    ])->assertUnprocessable()->assertJsonValidationErrors('keys');
});

test('a push subscription can be deleted by endpoint', function () {
    $user = userWithProfile();
    $user->updatePushSubscription('https://push.example.com/abc', 'k', 'a');

    $this->actingAs($user)->deleteJson('/api/v1/me/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc',
    ])->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('the public-key endpoint returns the configured VAPID key', function () {
    config(['webpush.vapid.public_key' => 'test-public-key']);

    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/me/push-subscriptions/public-key')
        ->assertOk()
        ->assertJsonPath('key', 'test-public-key');
});

test('the public-key endpoint returns 404 when VAPID is unset', function () {
    config(['webpush.vapid.public_key' => null]);

    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/me/push-subscriptions/public-key')
        ->assertNotFound();
});
