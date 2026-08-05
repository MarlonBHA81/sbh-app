<?php

use App\Models\DailyBrief;

test('the command pre-warms a brief for each active member and skips banned users', function () {
    $active = userWithProfile();
    $banned = userWithProfile([], ['banned_at' => now()]);

    $this->artisan('briefs:generate')->assertSuccessful();

    expect(DailyBrief::query()->count())->toBe(1);
    expect(DailyBrief::query()->where('profile_id', $active->profiles()->first()->id)->exists())->toBeTrue();
    expect(DailyBrief::query()->where('profile_id', $banned->profiles()->first()->id)->exists())->toBeFalse();
});

test('the command is safe to re-run and does not duplicate briefs', function () {
    userWithProfile();

    $this->artisan('briefs:generate')->assertSuccessful();
    $this->artisan('briefs:generate')->assertSuccessful();

    expect(DailyBrief::query()->count())->toBe(1);
});
