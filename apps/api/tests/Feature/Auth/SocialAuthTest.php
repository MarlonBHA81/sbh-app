<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeSocialiteUser(array $attributes = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $attributes['id'] ?? 'google-123';
    $user->name = $attributes['name'] ?? 'Social User';
    $user->email = $attributes['email'] ?? 'social@example.com';
    $user->nickname = $attributes['nickname'] ?? null;

    return $user;
}

function enableGoogle(): void
{
    config(['services.google.client_id' => 'test-client-id']);
}

test('social callback creates a user with a personal profile and verified email', function () {
    enableGoogle();

    Socialite::shouldReceive('driver->user')->andReturn(fakeSocialiteUser());

    $response = $this->get('/api/v1/auth/google/callback');

    $response->assertRedirect(config('app.frontend_url'));

    $user = User::where('email', 'social@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->personalProfile)->not->toBeNull()
        ->and($user->socialAccounts()->where('provider', 'google')->where('provider_id', 'google-123')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('social callback links an existing user by email', function () {
    enableGoogle();

    $existing = userWithProfile(userAttributes: ['email' => 'existing@example.com']);

    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser(['email' => 'existing@example.com', 'id' => 'google-999']));

    $this->get('/api/v1/auth/google/callback')->assertRedirect(config('app.frontend_url'));

    expect(User::count())->toBe(1)
        ->and(SocialAccount::where('user_id', $existing->id)->where('provider_id', 'google-999')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($existing);
});

test('social callback logs in an already-linked account without duplicating it', function () {
    enableGoogle();

    $existing = userWithProfile(userAttributes: ['email' => 'linked@example.com']);
    $existing->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'google-777']);

    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser(['email' => 'linked@example.com', 'id' => 'google-777']));

    $this->get('/api/v1/auth/google/callback')->assertRedirect(config('app.frontend_url'));

    expect(User::count())->toBe(1)
        ->and(SocialAccount::count())->toBe(1);

    $this->assertAuthenticatedAs($existing);
});

test('a provider without configured credentials returns 404', function () {
    config(['services.google.client_id' => null]);

    $this->get('/api/v1/auth/google/redirect')->assertNotFound();
    $this->get('/api/v1/auth/google/callback')->assertNotFound();
});

test('an unknown provider returns 404', function () {
    $this->get('/api/v1/auth/github/redirect')->assertNotFound();
});
