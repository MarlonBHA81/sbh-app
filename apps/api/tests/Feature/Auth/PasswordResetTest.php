<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('forgot password sends a reset link', function () {
    Notification::fake();

    $user = userWithProfile(userAttributes: ['email' => 'forgot@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'forgot@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password can be reset with a valid token', function () {
    Notification::fake();

    $user = userWithProfile(userAttributes: ['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com']);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'brand-new-password',
    ])->assertOk();
});
