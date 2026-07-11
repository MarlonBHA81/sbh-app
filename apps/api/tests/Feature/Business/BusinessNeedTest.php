<?php

use App\Models\BusinessCategory;
use App\Models\BusinessNeed;
use App\Models\Profile;

function businessActor(): array
{
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();
    $category = BusinessCategory::factory()->create();

    return [$user, $business, $category];
}

test('a business profile can list create update and delete needs', function () {
    [$user, $business, $category] = businessActor();

    $create = $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/me/business-needs', [
            'kind' => 'offering',
            'business_category_id' => $category->id,
            'description' => 'We supply fresh produce.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'offering')
        ->assertJsonPath('data.business_category.slug', $category->slug);

    $ulid = $create->json('data.ulid');

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/business-needs')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->patchJson("/api/v1/me/business-needs/{$ulid}", ['description' => 'Updated', 'active' => false])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated')
        ->assertJsonPath('data.active', false);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->deleteJson("/api/v1/me/business-needs/{$ulid}")
        ->assertNoContent();

    expect(BusinessNeed::query()->count())->toBe(0);
});

test('personal profiles cannot access business needs', function () {
    $user = userWithProfile();
    $category = BusinessCategory::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/me/business-needs')
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/v1/me/business-needs', [
            'kind' => 'seeking',
            'business_category_id' => $category->id,
            'description' => 'x',
        ])
        ->assertForbidden();
});

test('needs are capped at ten active per profile', function () {
    [$user, $business, $category] = businessActor();

    BusinessNeed::factory()->count(10)->create([
        'profile_id' => $business->id,
        'business_category_id' => $category->id,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/me/business-needs', [
            'kind' => 'offering',
            'business_category_id' => $category->id,
            'description' => 'one too many',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('active');
});

test('reactivating a need respects the active cap', function () {
    [$user, $business, $category] = businessActor();

    BusinessNeed::factory()->count(10)->create([
        'profile_id' => $business->id,
        'business_category_id' => $category->id,
        'active' => true,
    ]);

    $inactive = BusinessNeed::factory()->inactive()->create([
        'profile_id' => $business->id,
        'business_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->patchJson("/api/v1/me/business-needs/{$inactive->ulid}", ['active' => true])
        ->assertStatus(422);
});

test('need creation validates kind category and description', function () {
    [$user, $business] = businessActor();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/me/business-needs', [
            'kind' => 'invalid',
            'business_category_id' => 999999,
            'description' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kind', 'business_category_id', 'description']);
});

test('a profile cannot modify another profiles need', function () {
    [$user, $business, $category] = businessActor();
    $other = Profile::factory()->business()->create();
    $need = BusinessNeed::factory()->create([
        'profile_id' => $other->id,
        'business_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->patchJson("/api/v1/me/business-needs/{$need->ulid}", ['description' => 'hax'])
        ->assertForbidden();
});
