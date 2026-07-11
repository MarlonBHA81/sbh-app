<?php

use App\Models\Profile;
use App\Models\XpLedgerEntry;
use Database\Seeders\RankSeeder;
use Database\Seeders\XpActionSeeder;

function seedRanks(): void
{
    (new RankSeeder)->run();
}

test('the ranks endpoint returns all ranks ordered by position', function () {
    seedRanks();

    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/gamification/ranks')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.key', 'newbie')
        ->assertJsonPath('data.0.icon', '🌱')
        ->assertJsonPath('data.4.key', 'legend');
});

test('me/xp returns rank, next rank progress and today breakdown', function () {
    seedRanks();
    (new XpActionSeeder)->run();

    $user = userWithProfile();
    $profile = $user->personalProfile;
    $profile->forceFill(['xp_total' => 300])->save();

    // A ledger row today so the breakdown reflects real activity.
    XpLedgerEntry::factory()->create([
        'profile_id' => $profile->id,
        'action_key' => 'post_published',
        'points' => 10,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/me/xp')->assertOk();

    $response->assertJsonPath('xp_total', 300)
        ->assertJsonPath('rank.key', 'rising')      // 300 >= 100, < 500
        ->assertJsonPath('next_rank.key', 'contributor')
        ->assertJsonPath('next_rank.min_xp', 500);

    // progress from 100..500, at 300 => 50%
    expect((float) $response->json('next_rank.progress_pct'))->toBe(50.0);

    $published = collect($response->json('today'))->firstWhere('action_key', 'post_published');
    expect($published['times_today'])->toBe(1)
        ->and($published['earned_today'])->toBe(10)
        ->and($published['daily_cap'])->toBe(5)
        ->and($published['points'])->toBe(10);
});

test('me/xp reports the top rank with no next rank', function () {
    seedRanks();

    $user = userWithProfile();
    $user->personalProfile->forceFill(['xp_total' => 50000])->save();

    $this->actingAs($user)->getJson('/api/v1/me/xp')
        ->assertOk()
        ->assertJsonPath('rank.key', 'legend')
        ->assertJsonPath('next_rank', null);
});

test('all-time leaderboard orders by xp_total and appends the viewer when outside the top', function () {
    // 51 higher-XP profiles push the viewer out of the top 50.
    foreach (range(1, 51) as $i) {
        Profile::factory()->create(['xp_total' => 1000 + $i]);
    }

    $viewer = userWithProfile();
    $viewer->personalProfile->forceFill(['xp_total' => 5])->save();

    $response = $this->actingAs($viewer)->getJson('/api/v1/gamification/leaderboard?period=all')
        ->assertOk()
        ->assertJsonPath('period', 'all')
        ->assertJsonCount(50, 'entries');

    // Top entry has the highest xp; positions are 1-indexed.
    expect($response->json('entries.0.position'))->toBe(1)
        ->and($response->json('entries.0.xp'))->toBe(1051);

    // Viewer sits below the top 50.
    expect($response->json('me.profile.ulid'))->toBe($viewer->personalProfile->ulid)
        ->and($response->json('me.position'))->toBe(52)
        ->and($response->json('me.xp'))->toBe(5);
});

test('weekly leaderboard sums only the last 7 days of ledger points', function () {
    $recent = userWithProfile(['handle' => 'recent']);
    $stale = userWithProfile(['handle' => 'stale']);

    // Recent points count.
    XpLedgerEntry::factory()->create([
        'profile_id' => $recent->personalProfile->id,
        'points' => 30,
    ]);

    // Old points are excluded from the weekly window.
    $old = XpLedgerEntry::factory()->create([
        'profile_id' => $stale->personalProfile->id,
        'points' => 999,
    ]);
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    $response = $this->actingAs($recent)->getJson('/api/v1/gamification/leaderboard')
        ->assertOk()
        ->assertJsonPath('period', 'weekly');

    // Only the recent profile appears (stale's points fell outside the window).
    expect($response->json('entries'))->toHaveCount(1)
        ->and($response->json('entries.0.profile.handle'))->toBe('recent')
        ->and($response->json('entries.0.xp'))->toBe(30);
});

test('the gamification endpoints require authentication', function () {
    $this->getJson('/api/v1/gamification/leaderboard')->assertUnauthorized();
    $this->getJson('/api/v1/me/xp')->assertUnauthorized();
    $this->getJson('/api/v1/gamification/ranks')->assertUnauthorized();
});
