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

/*
|--------------------------------------------------------------------------
| Token lifetime + revocation (audit CB-2)
|--------------------------------------------------------------------------
| Tokens previously never expired and had no revocation route, so a leaked
| bearer token was permanent and the owner could do nothing about it.
*/

test('issued tokens carry an expiry', function () {
    config()->set('sanctum.expiration', 60 * 24 * 90);

    // The User model casts 'password' => 'hashed', so pass it in plain.
    $user = userWithProfile(userAttributes: ['password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-pass',
        'device_name' => 'iPhone',
    ])->assertCreated();

    expect($user->tokens()->first()->expires_at)->not->toBeNull();
});

test('a user can list their own tokens and see which is current', function () {
    $user = userWithProfile();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/tokens')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'iPhone')
        ->assertJsonPath('data.0.current', true);
});

test('a user can revoke a single token', function () {
    $user = userWithProfile();
    $keep = $user->createToken('Laptop')->plainTextToken;
    $doomedId = $user->createToken('Old phone')->accessToken->id;

    $this->withHeader('Authorization', "Bearer {$keep}")
        ->deleteJson("/api/v1/me/tokens/{$doomedId}")
        ->assertOk();

    expect($user->tokens()->count())->toBe(1);
});

test('a user cannot revoke another user token', function () {
    $mine = userWithProfile();
    $token = $mine->createToken('Mine')->plainTextToken;

    $stranger = userWithProfile();
    $strangerTokenId = $stranger->createToken('Theirs')->accessToken->id;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/me/tokens/{$strangerTokenId}")
        ->assertNotFound();

    expect($stranger->tokens()->count())->toBe(1);
});

test('revoking all tokens also drops the one making the request', function () {
    $user = userWithProfile();
    $token = $user->createToken('iPhone')->plainTextToken;
    $user->createToken('Laptop');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/me/tokens')
        ->assertOk()
        ->assertJsonPath('data.revoked', 2);

    expect($user->tokens()->count())->toBe(0);
});
