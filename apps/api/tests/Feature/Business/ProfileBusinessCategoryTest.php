<?php

use App\Models\BusinessCategory;
use App\Models\Profile;

test('business profile can set a business category', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();
    $category = BusinessCategory::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->patchJson("/api/v1/me/profiles/{$business->ulid}", [
            'business_category_id' => $category->id,
        ])
        ->assertOk();

    expect($business->fresh()->business_category_id)->toBe($category->id);

    $response->assertJsonPath('data.business_category.slug', $category->slug);
});

test('personal profile rejects a business category with 422', function () {
    $user = userWithProfile();
    $personal = $user->personalProfile;
    $category = BusinessCategory::factory()->create();

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$personal->ulid}", [
            'business_category_id' => $category->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_category_id');

    expect($personal->fresh()->business_category_id)->toBeNull();
});

test('business category must exist', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->patchJson("/api/v1/me/profiles/{$business->ulid}", [
            'business_category_id' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_category_id');
});
