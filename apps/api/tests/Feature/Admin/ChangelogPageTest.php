<?php

use App\Filament\Pages\Changelog;

test('the version config exposes a number and a release history', function () {
    expect(config('version.number'))->toBeString()->not->toBe('')
        ->and(config('version.releases'))->toBeArray()->not->toBeEmpty();
});

test('the changelog page is gated to super admins', function () {
    // Guest.
    expect(Changelog::canAccess())->toBeFalse();

    // Plain admin — not enough.
    $this->actingAs(adminWithProfile());
    expect(Changelog::canAccess())->toBeFalse();

    // Super admin.
    $this->actingAs(superAdminWithProfile());
    expect(Changelog::canAccess())->toBeTrue();
});

test('a super admin can open the changelog and see the current version', function () {
    $this->actingAs(superAdminWithProfile())
        ->get('/admin/changelog')
        ->assertSuccessful()
        ->assertSee('v'.config('version.number'));
});

test('a plain admin is forbidden from the changelog page', function () {
    $this->actingAs(adminWithProfile())
        ->get('/admin/changelog')
        ->assertForbidden();
});

test('the admin panel footer stamps the deployed version', function () {
    $this->actingAs(superAdminWithProfile())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee(config('version.number'));
});
