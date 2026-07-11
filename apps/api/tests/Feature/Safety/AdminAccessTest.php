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
