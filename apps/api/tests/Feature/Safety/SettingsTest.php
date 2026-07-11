<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

test('Setting::set persists and Setting::get reads it back', function () {
    Setting::set('registration_open', false);

    expect(Setting::get('registration_open'))->toBeFalse()
        ->and(Setting::query()->where('key', 'registration_open')->exists())->toBeTrue();
});

test('Setting::get returns the default when a key is missing', function () {
    expect(Setting::get('nonexistent_key', 'fallback'))->toBe('fallback');
});

test('Setting::get is cached', function () {
    Setting::set('max_business_profiles', 5);

    // Delete the underlying row; the cached value should still be served.
    Setting::query()->where('key', 'max_business_profiles')->delete();

    expect(Setting::get('max_business_profiles'))->toBe(5);

    Setting::forget('max_business_profiles');
    Cache::flush();

    expect(Setting::get('max_business_profiles', 3))->toBe(3);
});

test('the public status endpoint reports flags', function () {
    Setting::set('maintenance_message', 'Back soon');
    Setting::set('registration_open', false);

    $this->getJson('/api/v1/status')
        ->assertOk()
        ->assertJsonPath('maintenance_message', 'Back soon')
        ->assertJsonPath('registration_open', false);
});

test('registration is blocked with 403 when registration is closed', function () {
    Setting::set('registration_open', false);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'password' => 'Password123!',
    ])->assertForbidden();
});

test('registration works when registration is open', function () {
    Setting::set('registration_open', true);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Allowed User',
        'email' => 'allowed@example.com',
        'password' => 'Password123!',
    ])->assertCreated();
});

test('ProfileService reads max_business_profiles from settings', function () {
    Setting::set('max_business_profiles', 1);

    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/me/profiles', [
        'name' => 'First Biz',
    ])->assertCreated();

    // The second business profile exceeds the configured max of 1.
    $this->actingAs($user)->postJson('/api/v1/me/profiles', [
        'name' => 'Second Biz',
    ])->assertUnprocessable();
});
