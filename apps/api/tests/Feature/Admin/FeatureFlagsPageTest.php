<?php

use App\Filament\Pages\FeatureFlags;
use App\Models\Setting;
use App\Support\Features;
use Livewire\Livewire;

test('the feature flags page renders for a super admin', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)
        ->get('/admin/feature-flags')
        ->assertSuccessful()
        ->assertSee('Daily Business Brief')
        ->assertSee('Shop / marketplace');
});

test('the feature flags page is forbidden for a regular admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/feature-flags')->assertForbidden();
});

test('toggling a flag off persists to settings and resolves', function () {
    $admin = superAdminWithProfile();

    expect(Features::enabled('shop'))->toBeTrue();

    Livewire::actingAs($admin)
        ->test(FeatureFlags::class)
        ->fillForm(['shop' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('features.shop'))->toBeFalse()
        ->and(Features::enabled('shop'))->toBeFalse()
        // Untouched flags keep their default.
        ->and(Features::enabled('daily_brief'))->toBeTrue();
});
