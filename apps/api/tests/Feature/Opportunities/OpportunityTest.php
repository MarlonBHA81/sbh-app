<?php

use App\Models\Opportunity;

function makeOpportunity(array $attributes = []): Opportunity
{
    return Opportunity::create(array_merge([
        'type' => 'funding',
        'title' => 'Small business growth fund',
        'description' => 'Up to R50,000 for qualifying small businesses.',
        'organisation' => 'SEDA',
        'is_published' => true,
    ], $attributes));
}

test('the feed lists only published, open opportunities', function () {
    $user = userWithProfile();

    $open = makeOpportunity(['title' => 'Open grant', 'closes_at' => now()->addWeek()]);
    makeOpportunity(['title' => 'Draft grant', 'is_published' => false]);
    makeOpportunity(['title' => 'Closed grant', 'closes_at' => now()->subDay()]);

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Open grant')
        ->assertJsonPath('data.0.is_saved', false);

    expect($open->fresh()->published_at)->not->toBeNull();
});

test('the feed filters by type and industry', function () {
    $user = userWithProfile();

    makeOpportunity(['type' => 'tender', 'industry' => 'Retail', 'title' => 'Retail tender']);
    makeOpportunity(['type' => 'grant', 'industry' => 'Retail', 'title' => 'Retail grant']);
    makeOpportunity(['type' => 'tender', 'industry' => 'Technology', 'title' => 'Tech tender']);

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities?type=tender&industry=Retail')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Retail tender');
});

test('an unknown type filter is rejected', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities?type=nonsense')
        ->assertStatus(422);
});

test('a member can save, list and unsave an opportunity', function () {
    $user = userWithProfile();
    $opportunity = makeOpportunity();

    $this->actingAs($user)
        ->postJson("/api/v1/opportunities/{$opportunity->ulid}/save")
        ->assertCreated()
        ->assertJsonPath('data.saved', true);

    $this->actingAs($user)
        ->getJson('/api/v1/me/opportunities/saved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ulid', $opportunity->ulid)
        ->assertJsonPath('data.0.is_saved', true);

    // The main feed reflects the saved state too.
    $this->actingAs($user)
        ->getJson('/api/v1/opportunities')
        ->assertJsonPath('data.0.is_saved', true);

    $this->actingAs($user)
        ->deleteJson("/api/v1/opportunities/{$opportunity->ulid}/save")
        ->assertNoContent();

    $this->actingAs($user)
        ->getJson('/api/v1/me/opportunities/saved')
        ->assertJsonCount(0, 'data');
});

test('a draft opportunity cannot be viewed or saved', function () {
    $user = userWithProfile();
    $draft = makeOpportunity(['is_published' => false]);

    $this->actingAs($user)
        ->getJson("/api/v1/opportunities/{$draft->ulid}")
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson("/api/v1/opportunities/{$draft->ulid}/save")
        ->assertNotFound();
});

test('opportunities require authentication', function () {
    makeOpportunity();

    $this->getJson('/api/v1/opportunities')->assertUnauthorized();
});

test('the official flag and source surface in the feed and detail', function () {
    $user = userWithProfile();

    $official = makeOpportunity([
        'title' => 'Verified grant',
        'source' => 'SEDA',
        'source_url' => 'https://www.seda.org.za',
        'is_official' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities')
        ->assertOk()
        ->assertJsonPath('data.0.is_official', true)
        ->assertJsonPath('data.0.source', 'SEDA')
        ->assertJsonPath('data.0.source_url', 'https://www.seda.org.za');

    $this->actingAs($user)
        ->getJson("/api/v1/opportunities/{$official->ulid}")
        ->assertOk()
        ->assertJsonPath('data.is_official', true)
        ->assertJsonPath('data.source', 'SEDA');
});

test('an ordinary opportunity is not official by default', function () {
    $user = userWithProfile();
    makeOpportunity(['title' => 'Plain grant']);

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities')
        ->assertJsonPath('data.0.is_official', false)
        ->assertJsonPath('data.0.source', null);
});
