<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;

test('the user tier helpers reflect the flags', function () {
    $super = superAdminWithProfile();
    $admin = adminWithProfile();
    $member = userWithProfile();

    expect($super->isSuperAdmin())->toBeTrue()->and($super->isAdmin())->toBeTrue();
    expect($admin->isSuperAdmin())->toBeFalse()->and($admin->isAdmin())->toBeTrue();
    expect($member->isSuperAdmin())->toBeFalse()->and($member->isAdmin())->toBeFalse();
});

test('a plain admin cannot see the make-admin action', function () {
    $admin = adminWithProfile();
    $target = userWithProfile();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableActionHidden('toggle_admin', $target);
});

test('a super admin can promote a member to admin', function () {
    $super = superAdminWithProfile();
    $target = userWithProfile();

    Livewire::actingAs($super)
        ->test(ListUsers::class)
        ->assertTableActionVisible('toggle_admin', $target)
        ->callTableAction('toggle_admin', $target);

    expect($target->fresh()->is_admin)->toBeTrue();
});

test('the make-admin action is hidden for a super-admin record', function () {
    $super = superAdminWithProfile();
    $other = superAdminWithProfile();

    // A super admin's admin flag is managed via the super-admin action, not this one.
    Livewire::actingAs($super)
        ->test(ListUsers::class)
        ->assertTableActionHidden('toggle_admin', $other);
});

test('a plain admin can still ban a member (moderation stays admin-level)', function () {
    $admin = adminWithProfile();
    $target = userWithProfile();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableActionVisible('ban', $target)
        ->callTableAction('ban', $target, ['reason' => 'Spam']);

    expect($target->fresh()->isBanned())->toBeTrue();
});
