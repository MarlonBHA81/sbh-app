<?php

use App\Models\Block;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Profile;

/**
 * Fill page 1 of the for-you feed with high-scoring organic posts so promoted
 * posts (low score) never rank onto page 1 organically — that isolates the
 * injection behaviour from organic ranking.
 */
function fillOrganicPage(Profile $author, int $count = 20): void
{
    Post::factory()->count($count)->create([
        'profile_id' => $author->id,
        'score' => 1000,
        'published_at' => now()->subHour(),
    ]);
}

function promotedPost(Profile $author, array $postAttributes = [], array $campaignAttributes = []): Campaign
{
    $post = Post::factory()->create(array_merge([
        'profile_id' => $author->id,
        'score' => 1,
        'published_at' => now()->subHour(),
    ], $postAttributes));

    return Campaign::factory()->create(array_merge([
        'profile_id' => $author->id,
        'post_id' => $post->id,
        'status' => Campaign::STATUS_ACTIVE,
        'budget_cents' => 10000,
        'spent_cents' => 0,
        'ends_at' => now()->addDays(5),
    ], $campaignAttributes));
}

test('an eligible campaign is injected with a promoted flag and campaign ulid', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile);
    $campaign = promotedPost($author->personalProfile);

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    $promoted = $data->firstWhere('promoted', true);

    expect($promoted)->not->toBeNull()
        ->and($promoted['campaign_ulid'])->toBe($campaign->ulid)
        ->and($promoted['ulid'])->toBe($campaign->post->ulid);

    // Organic posts carry the flag too, but false.
    expect($data->firstWhere('promoted', false))->not->toBeNull();
});

test('a page of twenty injects at most two promoted posts', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile);

    foreach (range(1, 5) as $ignored) {
        promotedPost($author->personalProfile);
    }

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    expect($data->where('promoted', true)->count())->toBeLessThanOrEqual(2)
        ->and($data->where('promoted', true)->count())->toBe(2);
});

test('a campaign promoting the viewers own post is not injected', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile);
    promotedPost($viewer->personalProfile);

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    expect($data->where('promoted', true)->count())->toBe(0);
});

test('a campaign from a blocked author is not injected', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile);
    promotedPost($author->personalProfile);

    Block::create([
        'blocker_profile_id' => $viewer->personalProfile->id,
        'blocked_profile_id' => $author->personalProfile->id,
    ]);

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    expect($data->where('promoted', true)->count())->toBe(0);
});

test('a paused or exhausted campaign is not injected', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile);
    promotedPost($author->personalProfile, [], ['status' => Campaign::STATUS_PAUSED]);
    promotedPost($author->personalProfile, [], ['budget_cents' => 5000, 'spent_cents' => 5000]);

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    expect($data->where('promoted', true)->count())->toBe(0);
});

test('a promoted post already present organically is not injected twice', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();

    fillOrganicPage($author->personalProfile, 19);

    // High score so it ranks onto page 1 organically.
    $campaign = promotedPost($author->personalProfile, ['score' => 1000]);

    $data = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->assertOk()->json('data'));

    expect($data->where('ulid', $campaign->post->ulid)->count())->toBe(1)
        ->and($data->where('promoted', true)->count())->toBe(0);
});
