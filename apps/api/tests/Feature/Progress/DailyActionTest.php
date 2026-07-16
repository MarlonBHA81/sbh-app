<?php

use App\Models\DailyAction;
use App\Models\XpAction;
use App\Services\Gamification\GamificationService as G;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    XpAction::query()->create([
        'key' => G::DAILY_ACTION_COMPLETED,
        'label' => 'Completed the daily challenge',
        'points' => 15,
        'daily_cap' => 1,
    ]);
});

test('today returns the daily action, uncompleted, with a zero streak', function () {
    DailyAction::create(['title' => 'Ask for a testimonial', 'is_active' => true]);
    $user = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/me/daily')
        ->assertOk()
        ->assertJsonPath('data.action.title', 'Ask for a testimonial')
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.streak.current_days', 0);
});

test('completing the daily action awards xp and starts a streak', function () {
    DailyAction::create(['title' => 'Do the thing', 'is_active' => true]);
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/daily/complete')
        ->assertOk()
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.streak.current_days', 1)
        ->assertJsonPath('data.streak.ends_today', false);

    expect($user->personalProfile->fresh()->xp_total)->toBe(15);
});

test('completing twice in a day is idempotent', function () {
    DailyAction::create(['title' => 'Do the thing', 'is_active' => true]);
    $user = userWithProfile();
    $profile = $user->personalProfile;

    $this->actingAs($user)->postJson('/api/v1/me/daily/complete')->assertOk();
    $this->actingAs($user)->postJson('/api/v1/me/daily/complete')
        ->assertOk()
        ->assertJsonPath('data.streak.current_days', 1);

    expect($profile->fresh()->xp_total)->toBe(15);
    expect(
        DB::table('daily_action_completions')->where('profile_id', $profile->id)->count(),
    )->toBe(1);
});

test('a consecutive day increments the streak, a gap resets it', function () {
    $user = userWithProfile();
    $profile = $user->personalProfile;

    // A 5-day streak that last acted yesterday, then acts today.
    $profile->update([
        'streak_count' => 5,
        'streak_last_day' => now()->subDay()->toDateString(),
    ]);
    $profile->advanceStreak();
    expect($profile->fresh()->streak_count)->toBe(6);

    // A gap: last acted three days ago, then acts today.
    $profile->update([
        'streak_count' => 6,
        'streak_last_day' => now()->subDays(3)->toDateString(),
    ]);
    $profile->advanceStreak();
    expect($profile->fresh()->streak_count)->toBe(1);
});

test('the streak endpoint returns null when there is no streak', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/me/streak')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('the streak endpoint reports at-risk when acted yesterday', function () {
    $user = userWithProfile();
    $user->personalProfile->update([
        'streak_count' => 3,
        'streak_last_day' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/me/streak')
        ->assertOk()
        ->assertJsonPath('data.current_days', 3)
        ->assertJsonPath('data.ends_today', true);
});

test('the daily endpoints require authentication', function () {
    $this->getJson('/api/v1/me/daily')->assertUnauthorized();
});
