<?php

use App\Events\XpAwarded;
use App\Models\Badge;
use App\Models\Post;
use App\Models\Rank;
use App\Models\XpAction;
use App\Models\XpLedgerEntry;
use App\Notifications\RankUnlocked;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

function service(): GamificationService
{
    return app(GamificationService::class);
}

function makeAction(string $key, int $points = 10, ?int $cap = null, bool $enabled = true): XpAction
{
    return XpAction::factory()->create([
        'key' => $key,
        'points' => $points,
        'daily_cap' => $cap,
        'enabled' => $enabled,
    ]);
}

test('award writes a ledger row and increments xp_total', function () {
    $profile = userWithProfile()->personalProfile;
    makeAction('demo', 10);

    $entry = service()->award($profile, 'demo');

    expect($entry)->toBeInstanceOf(XpLedgerEntry::class)
        ->and($entry->points)->toBe(10)
        ->and($profile->fresh()->xp_total)->toBe(10)
        ->and(XpLedgerEntry::where('profile_id', $profile->id)->count())->toBe(1);
});

test('award is a no-op for a missing action', function () {
    $profile = userWithProfile()->personalProfile;

    expect(service()->award($profile, 'nope'))->toBeNull()
        ->and($profile->fresh()->xp_total)->toBe(0);
});

test('award is a no-op for a disabled action', function () {
    $profile = userWithProfile()->personalProfile;
    makeAction('demo', 10, enabled: false);

    expect(service()->award($profile, 'demo'))->toBeNull()
        ->and($profile->fresh()->xp_total)->toBe(0);
});

test('daily cap stops awards once reached', function () {
    $profile = userWithProfile()->personalProfile;
    makeAction('demo', 10, cap: 2);

    expect(service()->award($profile, 'demo'))->not->toBeNull();
    expect(service()->award($profile, 'demo'))->not->toBeNull();
    expect(service()->award($profile, 'demo'))->toBeNull();

    expect($profile->fresh()->xp_total)->toBe(20)
        ->and(XpLedgerEntry::where('profile_id', $profile->id)->count())->toBe(2);
});

test('subject bound awards are idempotent across like unlike relike', function () {
    $author = userWithProfile()->personalProfile;
    $post = Post::factory()->create(['profile_id' => $author->id]);
    makeAction('like_received', 2, cap: 50);

    // like
    expect(service()->award($author, 'like_received', $post))->not->toBeNull();
    // unlike (no clawback) then relike -> still only one award for this subject
    expect(service()->award($author, 'like_received', $post))->toBeNull();
    expect(service()->award($author, 'like_received', $post))->toBeNull();

    expect($author->fresh()->xp_total)->toBe(2)
        ->and(XpLedgerEntry::where('action_key', 'like_received')->count())->toBe(1);
});

test('negative points actions reduce xp_total', function () {
    $profile = userWithProfile()->personalProfile;
    $profile->forceFill(['xp_total' => 100])->save();
    makeAction('penalty', -30);

    $entry = service()->award($profile, 'penalty');

    expect($entry->points)->toBe(-30)
        ->and($profile->fresh()->xp_total)->toBe(70);
});

test('award broadcasts XpAwarded with the toast payload', function () {
    Event::fake([XpAwarded::class]);

    $profile = userWithProfile()->personalProfile;
    makeAction('demo', 7);

    service()->award($profile, 'demo');

    Event::assertDispatched(XpAwarded::class, function (XpAwarded $e) use ($profile) {
        return $e->profile->id === $profile->id
            && $e->points === 7
            && $e->actionKey === 'demo'
            && $e->xpTotal === 7;
    });
});

test('reaching a rank threshold exactly sets rank, attaches badge and notifies', function () {
    Notification::fake();

    $profile = userWithProfile()->personalProfile;

    $badge = Badge::factory()->create(['kind' => 'rank']);
    $newbie = Rank::factory()->create(['key' => 'newbie', 'min_xp' => 0, 'position' => 0]);
    $rising = Rank::factory()->create(['key' => 'rising', 'min_xp' => 100, 'position' => 1, 'badge_id' => $badge->id]);

    makeAction('demo', 100);

    service()->award($profile, 'demo');

    $profile->refresh();

    expect($profile->xp_total)->toBe(100)
        ->and($profile->rank_id)->toBe($rising->id)
        ->and($profile->badges()->where('badges.id', $badge->id)->exists())->toBeTrue();

    Notification::assertSentTo($profile->user, RankUnlocked::class, function (RankUnlocked $n) use ($rising) {
        return $n->rank->id === $rising->id;
    });
});

test('rank promotion swaps the lower rank badge for the new one', function () {
    $profile = userWithProfile()->personalProfile;

    $lowBadge = Badge::factory()->create(['kind' => 'rank', 'key' => 'rank_low']);
    $midBadge = Badge::factory()->create(['kind' => 'rank', 'key' => 'rank_mid']);
    $catBadge = Badge::factory()->create(['kind' => 'category', 'key' => 'cat']);

    $low = Rank::factory()->create(['key' => 'low', 'min_xp' => 0, 'position' => 0, 'badge_id' => $lowBadge->id]);
    Rank::factory()->create(['key' => 'mid', 'min_xp' => 100, 'position' => 1, 'badge_id' => $midBadge->id]);

    // Pre-existing state: low rank badge + an unrelated category badge.
    $profile->badges()->attach([$lowBadge->id => ['awarded_at' => now()], $catBadge->id => ['awarded_at' => now()]]);
    $profile->forceFill(['rank_id' => $low->id])->save();

    makeAction('demo', 120);
    service()->award($profile, 'demo');

    $ids = $profile->badges()->pluck('badges.id');

    expect($ids)->toContain($midBadge->id)
        ->and($ids)->not->toContain($lowBadge->id) // lower rank badge detached
        ->and($ids)->toContain($catBadge->id);     // non-rank badge untouched
});

test('a multi-rank jump lands on the highest qualifying rank', function () {
    Notification::fake();

    $profile = userWithProfile()->personalProfile;

    Rank::factory()->create(['key' => 'a', 'min_xp' => 0, 'position' => 0]);
    Rank::factory()->create(['key' => 'b', 'min_xp' => 100, 'position' => 1]);
    $legend = Rank::factory()->create(['key' => 'c', 'min_xp' => 1000, 'position' => 2]);

    makeAction('demo', 5000);
    service()->award($profile, 'demo');

    expect($profile->fresh()->rank_id)->toBe($legend->id);
});
