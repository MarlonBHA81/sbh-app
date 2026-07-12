<?php

use App\Models\Profile;
use App\Models\User;

/**
 * The banned-account message is a convenient user-facing string that flows
 * through EnsureNotBanned after SetLocale has resolved the request locale.
 */
function bannedUser(?string $locale): User
{
    $user = User::factory()->create([
        'banned_at' => now(),
        'ban_reason' => 'test',
        'locale' => $locale,
    ]);

    Profile::factory()->for($user)->create();

    return $user->fresh();
}

test('the Accept-Language header switches the message locale', function () {
    $user = bannedUser(locale: null);

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'es'])
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tu cuenta ha sido suspendida.');
});

test('the stored user locale preference wins over the header', function () {
    $user = bannedUser(locale: 'fr');

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'es'])
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Votre compte a été banni.');
});

test('an unsupported language falls back to English', function () {
    $user = bannedUser(locale: null);

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'de'])
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account has been banned.');
});

test('PATCH /me rejects an unsupported locale', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->patchJson('/api/v1/me', ['locale' => 'de'])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->patchJson('/api/v1/me', ['locale' => 'ar'])
        ->assertOk();
});
