<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('make:admin creates a new admin user with a profile', function () {
    Artisan::call('make:admin', [
        'email' => 'newadmin@example.com',
        '--name' => 'New Admin',
        '--password' => 'Password123!',
    ]);

    $user = User::query()->where('email', 'newadmin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->personalProfile)->not->toBeNull();
});

test('make:admin promotes an existing user', function () {
    $user = userWithProfile([], []);
    $email = $user->email;

    Artisan::call('make:admin', ['email' => $email]);

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('a non-admin cannot reach the Filament panel', function () {
    $user = userWithProfile();

    $response = $this->actingAs($user)->get('/admin');

    // Filament aborts (403) for users failing canAccessPanel.
    expect($response->status())->toBe(403);
});

test('an admin can reach the Filament panel', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

test('admin resource and settings pages render', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin);

    foreach ([
        '/admin/users',
        '/admin/profiles',
        '/admin/posts',
        '/admin/reports',
        '/admin/topics',
        '/admin/badges',
        '/admin/manage-settings',
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

test('canAccessPanel gates on is_admin', function () {
    $panel = filament()->getPanel('admin');

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $plain = User::factory()->create();

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($plain->canAccessPanel($panel))->toBeFalse();
});

test('make:admin --super sets both admin flags on a new user', function () {
    Artisan::call('make:admin', [
        'email' => 'super@example.com',
        '--name' => 'Super',
        '--password' => 'Password123!',
        '--super' => true,
    ]);

    $user = User::query()->where('email', 'super@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->is_super_admin)->toBeTrue()
        ->and($user->personalProfile)->not->toBeNull();
});

test('make:admin --super promotes an existing user to super admin', function () {
    $user = userWithProfile();

    Artisan::call('make:admin', ['email' => $user->email, '--super' => true]);

    expect($user->fresh()->is_admin)->toBeTrue()
        ->and($user->fresh()->is_super_admin)->toBeTrue();
});

test('make:admin without --super does not grant super admin', function () {
    $user = userWithProfile();

    Artisan::call('make:admin', ['email' => $user->email]);

    expect($user->fresh()->is_admin)->toBeTrue()
        ->and($user->fresh()->is_super_admin)->toBeFalse();
});

test('the users table exposes the super admin column', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertSee('Super admin');
});

test('the integrations page is forbidden for a regular admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/integrations')->assertForbidden();
});

test('the integrations page renders for a super admin', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)->get('/admin/integrations')->assertSuccessful();
});

test('the platform analytics page is forbidden for a regular admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/platform-analytics')->assertForbidden();
});

test('the platform analytics page renders for a super admin', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)->get('/admin/platform-analytics')->assertSuccessful();
});
