<?php

use App\Models\LibraryResource;

function makeResource(array $attributes = []): LibraryResource
{
    return LibraryResource::create(array_merge([
        'type' => 'template',
        'category' => 'finance',
        'title' => 'Cash-flow forecast template',
        'description' => 'A ready-to-use monthly cash-flow template.',
        'url' => 'https://example.com/template',
        'is_published' => true,
    ], $attributes));
}

test('the library lists only published resources', function () {
    $user = userWithProfile();

    makeResource(['title' => 'Live resource']);
    makeResource(['title' => 'Draft resource', 'is_published' => false]);

    $this->actingAs($user)
        ->getJson('/api/v1/resources')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Live resource')
        ->assertJsonPath('data.0.is_saved', false);
});

test('the library filters by category and type', function () {
    $user = userWithProfile();

    makeResource(['category' => 'marketing', 'type' => 'toolkit', 'title' => 'Marketing toolkit']);
    makeResource(['category' => 'marketing', 'type' => 'checklist', 'title' => 'Marketing checklist']);
    makeResource(['category' => 'finance', 'type' => 'toolkit', 'title' => 'Finance toolkit']);

    $this->actingAs($user)
        ->getJson('/api/v1/resources?category=marketing&type=toolkit')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Marketing toolkit');
});

test('the library searches title and description', function () {
    $user = userWithProfile();

    makeResource(['title' => 'Hiring checklist', 'description' => 'For your first employee.']);
    makeResource(['title' => 'Invoice pack', 'description' => 'Send professional invoices.']);

    $this->actingAs($user)
        ->getJson('/api/v1/resources?q=hiring')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Hiring checklist');
});

test('an unknown category filter is rejected', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->getJson('/api/v1/resources?category=nonsense')
        ->assertStatus(422);
});

test('a member can save, list and unsave a resource', function () {
    $user = userWithProfile();
    $resource = makeResource();

    $this->actingAs($user)
        ->postJson("/api/v1/resources/{$resource->ulid}/save")
        ->assertCreated()
        ->assertJsonPath('data.saved', true);

    $this->actingAs($user)
        ->getJson('/api/v1/me/resources/saved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ulid', $resource->ulid)
        ->assertJsonPath('data.0.is_saved', true);

    $this->actingAs($user)
        ->getJson('/api/v1/resources')
        ->assertJsonPath('data.0.is_saved', true);

    $this->actingAs($user)
        ->deleteJson("/api/v1/resources/{$resource->ulid}/save")
        ->assertNoContent();

    $this->actingAs($user)
        ->getJson('/api/v1/me/resources/saved')
        ->assertJsonCount(0, 'data');
});

test('a draft resource cannot be viewed or saved', function () {
    $user = userWithProfile();
    $draft = makeResource(['is_published' => false]);

    $this->actingAs($user)
        ->getJson("/api/v1/resources/{$draft->ulid}")
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson("/api/v1/resources/{$draft->ulid}/save")
        ->assertNotFound();
});

test('resources require authentication', function () {
    makeResource();

    $this->getJson('/api/v1/resources')->assertUnauthorized();
});
