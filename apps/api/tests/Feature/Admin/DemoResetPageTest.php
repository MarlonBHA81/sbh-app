<?php

use App\Filament\Pages\DemoReset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('the demo & reset page is forbidden for a regular admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/demo-reset')->assertForbidden();
});

test('the demo & reset page renders for a super admin', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)
        ->get('/admin/demo-reset')
        ->assertSuccessful()
        ->assertSee('Load demo content')
        ->assertSee('Danger zone');
});

test('the page is not reachable for a non-admin at all', function () {
    $user = userWithProfile();

    $this->actingAs($user)->get('/admin/demo-reset')->assertForbidden();
});

test('the load demo action seeds the demo dataset', function () {
    $admin = superAdminWithProfile();

    Livewire::actingAs($admin)
        ->test(DemoReset::class)
        ->callAction('loadDemo')
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(User::query()->where('email', 'like', '%@demo.sbh')->count())->toBeGreaterThanOrEqual(12);
});

test('the master reset action requires the literal RESET text', function () {
    $admin = superAdminWithProfile();
    $user = userWithProfile();

    Livewire::actingAs($admin)
        ->test(DemoReset::class)
        ->callAction('masterReset', ['confirmation' => 'nope'])
        ->assertHasActionErrors(['confirmation']);

    expect($user->fresh())->not->toBeNull();
});

test('the master reset action runs with the correct confirmation', function () {
    $admin = superAdminWithProfile();
    $user = userWithProfile();

    Livewire::actingAs($admin)
        ->test(DemoReset::class)
        ->callAction('masterReset', ['confirmation' => 'RESET'])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($user->fresh())->toBeNull()
        ->and($admin->fresh())->not->toBeNull();
});
