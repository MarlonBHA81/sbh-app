<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Models\Coupon;
use Livewire\Livewire;

test('the commerce admin pages render for an admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/stores/create')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/products/create')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/coupons')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/coupons/create')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/bug-reports')->assertSuccessful();
    // Renders with the CSV ImportAction wired in.
    $this->actingAs($admin)->get('/admin/opportunities')->assertSuccessful();
});

test('an admin can create a coupon from the panel', function () {
    $admin = adminWithProfile();

    Livewire::actingAs($admin)
        ->test(CreateCoupon::class)
        ->fillForm([
            'code' => 'launch25',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 25,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::sole();
    // Codes are stored upper-cased.
    expect($coupon->code)->toBe('LAUNCH25')
        ->and($coupon->type)->toBe(Coupon::TYPE_PERCENT)
        ->and($coupon->value)->toBe(25);
});
