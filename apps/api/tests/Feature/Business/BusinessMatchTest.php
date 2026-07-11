<?php

use App\Models\Block;
use App\Models\BusinessCategory;
use App\Models\BusinessNeed;
use App\Models\Profile;
use App\Models\User;
use App\Services\Business\MatchmakingService;

/**
 * Create a business profile owned by a fresh user, plus return the acting user.
 *
 * @return array{0: User, 1: Profile}
 */
function matchViewer(array $attrs = []): array
{
    $user = userWithProfile();
    $profile = Profile::factory()->business()->for($user)->create($attrs);

    return [$user, $profile];
}

function needFor(Profile $profile, string $kind, BusinessCategory $category, array $attrs = []): BusinessNeed
{
    return BusinessNeed::factory()->create(array_merge([
        'profile_id' => $profile->id,
        'kind' => $kind,
        'business_category_id' => $category->id,
    ], $attrs));
}

test('only business profiles can access matches', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/business/matches')->assertForbidden();
});

test('reciprocal offering and seeking match each other', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [$userB, $b] = matchViewer();

    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($b, BusinessNeed::KIND_SEEKING, $category);

    $aMatches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->assertOk()->json('data');
    expect(collect($aMatches)->pluck('profile.handle'))->toContain($b->handle);

    $bMatches = $this->actingAs($userB)->withHeader('X-Profile-Id', $b->ulid)
        ->getJson('/api/v1/business/matches')->assertOk()->json('data');
    expect(collect($bMatches)->pluck('profile.handle'))->toContain($a->handle);
});

test('same kind needs do not match', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [, $b] = matchViewer();

    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($b, BusinessNeed::KIND_OFFERING, $category);

    $matches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->assertOk()->json('data');

    expect($matches)->toBeEmpty();
});

test('needs in different categories do not match', function () {
    $catX = BusinessCategory::factory()->create();
    $catY = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [, $b] = matchViewer();

    needFor($a, BusinessNeed::KIND_OFFERING, $catX);
    needFor($b, BusinessNeed::KIND_SEEKING, $catY);

    $matches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->assertOk()->json('data');

    expect($matches)->toBeEmpty();
});

test('score sums category pairs city and country', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer(['city' => 'Cape Town', 'country_code' => 'ZA']);
    [, $sameCity] = matchViewer(['city' => 'Cape Town', 'country_code' => 'ZA']);
    [, $sameCountry] = matchViewer(['city' => 'Durban', 'country_code' => 'ZA']);
    [, $elsewhere] = matchViewer(['city' => 'Paris', 'country_code' => 'FR']);

    // One reciprocal pair with each.
    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($sameCity, BusinessNeed::KIND_SEEKING, $category);
    needFor($sameCountry, BusinessNeed::KIND_SEEKING, $category);
    needFor($elsewhere, BusinessNeed::KIND_SEEKING, $category);

    $matches = collect(
        $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
            ->getJson('/api/v1/business/matches')->assertOk()->json('data')
    )->keyBy('profile.handle');

    // 3 (pair) + 2 (city) + 1 (country) = 6
    expect($matches[$sameCity->handle]['score'])->toBe(6);
    // 3 (pair) + 1 (country) = 4
    expect($matches[$sameCountry->handle]['score'])->toBe(4);
    // 3 (pair) only
    expect($matches[$elsewhere->handle]['score'])->toBe(3);

    // Sorted by score descending.
    $scores = collect($this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->json('data'))->pluck('score');
    expect($scores->toArray())->toBe([6, 4, 3]);
});

test('multiple category pairs stack at three each', function () {
    $catX = BusinessCategory::factory()->create();
    $catY = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [, $b] = matchViewer();

    needFor($a, BusinessNeed::KIND_OFFERING, $catX);
    needFor($a, BusinessNeed::KIND_SEEKING, $catY);
    needFor($b, BusinessNeed::KIND_SEEKING, $catX);
    needFor($b, BusinessNeed::KIND_OFFERING, $catY);

    $match = collect(
        $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
            ->getJson('/api/v1/business/matches')->json('data')
    )->firstWhere('profile.handle', $b->handle);

    expect($match['score'])->toBe(6);
    expect($match['matches'])->toHaveCount(2);
});

test('blocked profiles are excluded from matches both ways', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [, $blockedByMe] = matchViewer();
    [, $blocksMe] = matchViewer();
    [, $ok] = matchViewer();

    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($blockedByMe, BusinessNeed::KIND_SEEKING, $category);
    needFor($blocksMe, BusinessNeed::KIND_SEEKING, $category);
    needFor($ok, BusinessNeed::KIND_SEEKING, $category);

    Block::create(['blocker_profile_id' => $a->id, 'blocked_profile_id' => $blockedByMe->id]);
    Block::create(['blocker_profile_id' => $blocksMe->id, 'blocked_profile_id' => $a->id]);

    $handles = collect(
        $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
            ->getJson('/api/v1/business/matches')->json('data')
    )->pluck('profile.handle');

    expect($handles)->toContain($ok->handle)
        ->not->toContain($blockedByMe->handle)
        ->not->toContain($blocksMe->handle);
});

test('own profiles are never matched', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    $sibling = Profile::factory()->business()->for($userA)->create();

    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($sibling, BusinessNeed::KIND_SEEKING, $category);

    $matches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->json('data');

    expect($matches)->toBeEmpty();
});

test('inactive needs are ignored on both sides', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    [, $b] = matchViewer();
    [, $c] = matchViewer();

    // My need inactive -> no matches at all.
    needFor($a, BusinessNeed::KIND_OFFERING, $category, ['active' => false]);
    needFor($b, BusinessNeed::KIND_SEEKING, $category);

    $matches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->json('data');
    expect($matches)->toBeEmpty();

    // Now give me an active need but the counterpart's is inactive.
    needFor($a, BusinessNeed::KIND_OFFERING, $category);
    needFor($c, BusinessNeed::KIND_SEEKING, $category, ['active' => false]);

    app(MatchmakingService::class)->forget($a);

    $handles = collect(
        $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
            ->getJson('/api/v1/business/matches')->json('data')
    )->pluck('profile.handle');

    expect($handles)->toContain($b->handle)->not->toContain($c->handle);
});

test('matches are capped at fifty profiles', function () {
    $category = BusinessCategory::factory()->create();

    [$userA, $a] = matchViewer();
    needFor($a, BusinessNeed::KIND_OFFERING, $category);

    foreach (range(1, 55) as $i) {
        [, $b] = matchViewer();
        needFor($b, BusinessNeed::KIND_SEEKING, $category);
    }

    $matches = $this->actingAs($userA)->withHeader('X-Profile-Id', $a->ulid)
        ->getJson('/api/v1/business/matches')->json('data');

    expect($matches)->toHaveCount(50);
});
