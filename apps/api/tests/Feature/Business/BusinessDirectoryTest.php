<?php

use App\Models\Block;
use App\Models\BusinessCategory;
use App\Models\Profile;
use App\Models\User;

function directoryViewer(): User
{
    return userWithProfile();
}

test('directory lists only business profiles with non-banned owners', function () {
    $viewer = directoryViewer();

    $business = Profile::factory()->business()->create(['name' => 'Acme Co']);
    $personal = Profile::factory()->create(['name' => 'A Person']);
    $bannedOwnerBusiness = Profile::factory()->business()->create(['name' => 'Banned Biz']);
    $bannedOwnerBusiness->user()->update(['banned_at' => now()]);

    $handles = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/directory')->assertOk()->json('data')
    )->pluck('handle');

    expect($handles)->toContain($business->handle)
        ->not->toContain($personal->handle)
        ->not->toContain($bannedOwnerBusiness->handle);
});

test('directory filters by category slug', function () {
    $viewer = directoryViewer();
    $cat = BusinessCategory::factory()->create(['slug' => 'plumbing']);
    $other = BusinessCategory::factory()->create(['slug' => 'baking']);

    $match = Profile::factory()->business()->create(['business_category_id' => $cat->id]);
    Profile::factory()->business()->create(['business_category_id' => $other->id]);

    $handles = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/directory?category=plumbing')->json('data')
    )->pluck('handle');

    expect($handles)->toContain($match->handle)->toHaveCount(1);
});

test('directory filters by q across name handle and bio', function () {
    $viewer = directoryViewer();

    $byName = Profile::factory()->business()->create(['name' => 'Sunshine Bakery', 'bio' => 'x']);
    $byBio = Profile::factory()->business()->create(['name' => 'Nope', 'bio' => 'we love sunshine']);
    Profile::factory()->business()->create(['name' => 'Nothing', 'bio' => 'nada']);

    $handles = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/directory?q=sunshine')->json('data')
    )->pluck('handle');

    expect($handles)->toContain($byName->handle)->toContain($byBio->handle)->toHaveCount(2);
});

test('directory filters by country and city', function () {
    $viewer = directoryViewer();

    $target = Profile::factory()->business()->create(['country_code' => 'ZA', 'city' => 'Cape Town']);
    Profile::factory()->business()->create(['country_code' => 'ZA', 'city' => 'Durban']);
    Profile::factory()->business()->create(['country_code' => 'US', 'city' => 'Cape Town']);

    $handles = collect(
        $this->actingAs($viewer)
            ->getJson('/api/v1/business/directory?country=ZA&city=Cape Town')
            ->json('data')
    )->pluck('handle');

    expect($handles)->toContain($target->handle)->toHaveCount(1);
});

test('directory orders by verified then followers', function () {
    $viewer = directoryViewer();

    $verified = Profile::factory()->business()->create(['is_verified' => true, 'followers_count' => 1]);
    $popular = Profile::factory()->business()->create(['is_verified' => false, 'followers_count' => 500]);
    $small = Profile::factory()->business()->create(['is_verified' => false, 'followers_count' => 2]);

    $handles = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/directory')->json('data')
    )->pluck('handle');

    expect($handles->first())->toBe($verified->handle);
    expect($handles->slice(1)->values()->first())->toBe($popular->handle);
    expect($handles->last())->toBe($small->handle);
});

test('directory hides profiles blocked in either direction', function () {
    $viewer = directoryViewer();

    $blockedByViewer = Profile::factory()->business()->create();
    Block::create([
        'blocker_profile_id' => $viewer->personalProfile->id,
        'blocked_profile_id' => $blockedByViewer->id,
    ]);

    $blockingViewer = Profile::factory()->business()->create();
    Block::create([
        'blocker_profile_id' => $blockingViewer->id,
        'blocked_profile_id' => $viewer->personalProfile->id,
    ]);

    $visible = Profile::factory()->business()->create();

    $handles = collect(
        $this->actingAs($viewer)->getJson('/api/v1/business/directory')->json('data')
    )->pluck('handle');

    expect($handles)->toContain($visible->handle)
        ->not->toContain($blockedByViewer->handle)
        ->not->toContain($blockingViewer->handle);
});
