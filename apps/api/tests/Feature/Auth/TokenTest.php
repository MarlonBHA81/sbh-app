<?php

test('a token is issued for valid credentials and grants api access', function () {
    userWithProfile(userAttributes: [
        'email' => 'mobile@example.com',
        'password' => 'super-secret-password',
    ]);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => 'mobile@example.com',
        'password' => 'super-secret-password',
        'device_name' => 'iphone-15',
    ]);

    $response->assertCreated()->assertJsonStructure(['token']);

    $this->getJson('/api/v1/me', [
        'Authorization' => 'Bearer '.$response->json('token'),
    ])->assertOk()->assertJsonPath('user.email', 'mobile@example.com');
});

test('token issuance fails with invalid credentials', function () {
    userWithProfile(userAttributes: [
        'email' => 'mobile@example.com',
        'password' => 'super-secret-password',
    ]);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'mobile@example.com',
        'password' => 'nope',
        'device_name' => 'iphone-15',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('token issuance is refused for banned users', function () {
    userWithProfile(userAttributes: [
        'email' => 'banned@example.com',
        'password' => 'super-secret-password',
        'banned_at' => now(),
        'ban_reason' => 'Fraud',
    ]);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'banned@example.com',
        'password' => 'super-secret-password',
        'device_name' => 'iphone-15',
    ])->assertForbidden()->assertJsonPath('ban_reason', 'Fraud');
});
