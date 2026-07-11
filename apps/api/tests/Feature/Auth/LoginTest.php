<?php

test('a user can login with valid credentials', function () {
    $user = userWithProfile(userAttributes: [
        'email' => 'login@example.com',
        'password' => 'super-secret-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'super-secret-password',
    ])->assertOk()->assertJsonPath('user.email', 'login@example.com');

    $this->assertAuthenticatedAs($user);
});

test('login fails with invalid credentials', function () {
    userWithProfile(userAttributes: [
        'email' => 'login@example.com',
        'password' => 'super-secret-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('banned users cannot login and receive the ban reason', function () {
    userWithProfile(userAttributes: [
        'email' => 'banned@example.com',
        'password' => 'super-secret-password',
        'banned_at' => now(),
        'ban_reason' => 'Spamming the feed',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'banned@example.com',
        'password' => 'super-secret-password',
    ])->assertForbidden()->assertJsonPath('ban_reason', 'Spamming the feed');

    $this->assertGuest();
});

test('banned users are rejected on authenticated routes', function () {
    $user = userWithProfile(userAttributes: [
        'banned_at' => now(),
        'ban_reason' => 'Abuse',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('ban_reason', 'Abuse');
});

test('a user can logout', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();
});
