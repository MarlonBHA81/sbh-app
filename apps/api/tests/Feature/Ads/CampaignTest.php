<?php

use App\Models\Campaign;
use App\Models\Post;
use App\Models\User;

function ownPost(User $user, array $attributes = []): Post
{
    return Post::factory()->create(array_merge([
        'profile_id' => $user->personalProfile->id,
    ], $attributes));
}

test('a creator can promote their own published public post', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    $response = $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertCreated();

    $response->assertJsonPath('data.status', Campaign::STATUS_ACTIVE)
        ->assertJsonPath('data.budget_cents', 10000)
        ->assertJsonPath('data.spent_cents', 0)
        ->assertJsonPath('data.remaining_cents', 10000)
        ->assertJsonPath('data.cpi_cents', 2)
        ->assertJsonPath('data.post.ulid', $post->ulid);

    expect(Campaign::query()->where('post_id', $post->id)->exists())->toBeTrue();
});

test('a campaign can be created without a budget (metrics only)', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'duration_days' => 7,
    ])->assertCreated()
        ->assertJsonPath('data.budget_cents', null)
        ->assertJsonPath('data.remaining_cents', null)
        ->assertJsonPath('data.link_clicks', 0)
        ->assertJsonPath('data.ctr_pct', 0)
        ->assertJsonPath('data.link_ctr_pct', 0);
});

test('a creator cannot promote a post they do not own', function () {
    $user = adminWithProfile();
    $other = adminWithProfile();
    $post = ownPost($other);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertStatus(422);
});

test('a draft post cannot be promoted', function () {
    $user = adminWithProfile();
    $post = ownPost($user, ['status' => Post::STATUS_DRAFT, 'published_at' => null]);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertStatus(422);
});

test('a followers-only post cannot be promoted', function () {
    $user = adminWithProfile();
    $post = ownPost($user, ['visibility' => Post::VISIBILITY_FOLLOWERS]);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertStatus(422);
});

test('budget must be within the configured bounds', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 4999,
        'duration_days' => 7,
    ])->assertStatus(422)->assertJsonValidationErrors('budget_cents');

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 500001,
        'duration_days' => 7,
    ])->assertStatus(422)->assertJsonValidationErrors('budget_cents');
});

test('duration must be between one and thirty days', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('duration_days');

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 31,
    ])->assertStatus(422)->assertJsonValidationErrors('duration_days');
});

test('a post may only have one non-completed campaign', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => $post->id,
        'status' => Campaign::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertStatus(409);
});

test('a post can be re-promoted once its previous campaign is completed', function () {
    $user = adminWithProfile();
    $post = ownPost($user);

    Campaign::factory()->completed()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => $post->id,
    ]);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertCreated();
});

test('a campaign can be paused and resumed', function () {
    $user = adminWithProfile();
    $campaign = Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
    ]);

    $this->actingAs($user)->patchJson("/api/v1/ads/campaigns/{$campaign->ulid}", [
        'status' => Campaign::STATUS_PAUSED,
    ])->assertOk()->assertJsonPath('data.status', Campaign::STATUS_PAUSED);

    $this->actingAs($user)->patchJson("/api/v1/ads/campaigns/{$campaign->ulid}", [
        'status' => Campaign::STATUS_ACTIVE,
    ])->assertOk()->assertJsonPath('data.status', Campaign::STATUS_ACTIVE);
});

test('a completed campaign cannot be reactivated', function () {
    $user = adminWithProfile();
    $campaign = Campaign::factory()->completed()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
    ]);

    $this->actingAs($user)->patchJson("/api/v1/ads/campaigns/{$campaign->ulid}", [
        'status' => Campaign::STATUS_ACTIVE,
    ])->assertStatus(422);
});

test('deleting a campaign ends it early by completing it', function () {
    $user = adminWithProfile();
    $campaign = Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
    ]);

    $this->actingAs($user)->deleteJson("/api/v1/ads/campaigns/{$campaign->ulid}")
        ->assertOk()
        ->assertJsonPath('data.status', Campaign::STATUS_COMPLETED);
});

test('a creator cannot view or manage another creators campaign', function () {
    $user = adminWithProfile();
    $other = adminWithProfile();
    $campaign = Campaign::factory()->create([
        'profile_id' => $other->personalProfile->id,
        'post_id' => ownPost($other)->id,
    ]);

    $this->actingAs($user)->getJson("/api/v1/ads/campaigns/{$campaign->ulid}")->assertNotFound();
    $this->actingAs($user)->deleteJson("/api/v1/ads/campaigns/{$campaign->ulid}")->assertNotFound();
});

test('the campaign index lists only my campaigns newest first', function () {
    $user = adminWithProfile();
    $other = adminWithProfile();

    $older = Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
    ]);
    $newer = Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
    ]);
    Campaign::factory()->create([
        'profile_id' => $other->personalProfile->id,
        'post_id' => ownPost($other)->id,
    ]);

    $ulids = collect($this->actingAs($user)->getJson('/api/v1/ads/campaigns')->assertOk()->json('data'))
        ->pluck('ulid');

    expect($ulids->all())->toBe([$newer->ulid, $older->ulid]);
});

test('the show endpoint returns a per-day series when requested', function () {
    $user = adminWithProfile();
    $campaign = Campaign::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'post_id' => ownPost($user)->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->addDays(2),
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/v1/ads/campaigns/{$campaign->ulid}?series=1")
        ->assertOk();

    expect($response->json('data.series'))->toBeArray()->not->toBeEmpty();
    expect($response->json('data.series.0'))->toHaveKeys(['date', 'impressions', 'clicks']);
});

test('a non-admin cannot list campaigns', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/ads/campaigns')->assertForbidden();
});

test('a non-admin cannot create a campaign', function () {
    $user = userWithProfile();
    $post = ownPost($user);

    $this->actingAs($user)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertForbidden();

    expect(Campaign::query()->count())->toBe(0);
});

test('a non-admin cannot show, update or delete a campaign', function () {
    $admin = adminWithProfile();
    $campaign = Campaign::factory()->create([
        'profile_id' => $admin->personalProfile->id,
        'post_id' => ownPost($admin)->id,
    ]);

    $user = userWithProfile();

    $this->actingAs($user)->getJson("/api/v1/ads/campaigns/{$campaign->ulid}")->assertForbidden();
    $this->actingAs($user)->patchJson("/api/v1/ads/campaigns/{$campaign->ulid}", [
        'status' => Campaign::STATUS_PAUSED,
    ])->assertForbidden();
    $this->actingAs($user)->deleteJson("/api/v1/ads/campaigns/{$campaign->ulid}")->assertForbidden();
});

test('an admin can create and manage campaigns', function () {
    $admin = adminWithProfile();
    $post = ownPost($admin);

    $this->actingAs($admin)->postJson('/api/v1/ads/campaigns', [
        'post_ulid' => $post->ulid,
        'budget_cents' => 10000,
        'duration_days' => 7,
    ])->assertCreated();
});
