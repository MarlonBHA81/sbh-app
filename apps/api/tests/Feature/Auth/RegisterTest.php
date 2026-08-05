<?php

use App\Models\Profile;
use App\Models\User;

test('register creates a user with a personal profile and logs in', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'super-secret-password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'john@example.com')
        ->assertJsonPath('profile.kind', 'personal')
        ->assertJsonPath('profile.handle', 'john_doe');

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->personalProfile)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('register accepts an explicit handle', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'super-secret-password',
        'handle' => 'jane_awesome',
    ])->assertCreated()->assertJsonPath('profile.handle', 'jane_awesome');
});

test('register dedupes an auto-generated handle that is taken', function () {
    Profile::factory()->create(['handle' => 'john_doe']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john2@example.com',
        'password' => 'super-secret-password',
    ])->assertCreated()->assertJsonPath('profile.handle', 'john_doe1');
});

test('register rejects an invalid handle', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => 'super-secret-password',
        'handle' => 'ab',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');
});

test('register rejects a reserved handle', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => 'super-secret-password',
        'handle' => 'admin',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');
});

test('register rejects a taken handle', function () {
    Profile::factory()->create(['handle' => 'taken_handle']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => 'super-secret-password',
        'handle' => 'taken_handle',
    ])->assertUnprocessable()->assertJsonValidationErrors('handle');
});

test('register is rate limited to five attempts per minute', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'super-secret-password',
        ]);
    }

    $this->postJson('/api/v1/auth/register', [
        'name' => 'User 6',
        'email' => 'user6@example.com',
        'password' => 'super-secret-password',
    ])->assertStatus(429);
});
