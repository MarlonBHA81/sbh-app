<?php

use App\Models\Masterclass;
use Database\Seeders\MasterclassSeeder;

function makeMasterclass(array $attributes = []): Masterclass
{
    return Masterclass::create(array_merge([
        'title' => 'Retail Accelerator',
        'description' => 'A cohort programme.',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeeks(6),
        'is_published' => true,
    ], $attributes));
}

test('the list returns only published, not-yet-finished masterclasses', function () {
    $user = userWithProfile();

    makeMasterclass(['title' => 'Live']);
    makeMasterclass(['title' => 'Draft', 'is_published' => false]);
    makeMasterclass(['title' => 'Finished', 'starts_at' => now()->subWeeks(4), 'ends_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->getJson('/api/v1/masterclasses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Live')
        ->assertJsonPath('data.0.enrolled', false);
});

test('a member can enrol and withdraw', function () {
    $user = userWithProfile();
    $class = makeMasterclass();

    $this->actingAs($user)
        ->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")
        ->assertCreated()
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.participants_count', 1);

    $this->actingAs($user)
        ->deleteJson("/api/v1/masterclasses/{$class->ulid}/enrol")
        ->assertNoContent();

    expect($class->participants()->count())->toBe(0);
});

test('enrolment respects the seat capacity', function () {
    $taken = userWithProfile();
    $late = userWithProfile();
    $class = makeMasterclass(['capacity' => 1]);

    $this->actingAs($taken)->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")->assertCreated();

    $this->actingAs($late)
        ->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")
        ->assertStatus(422);

    expect($class->participants()->count())->toBe(1);
});

test('you cannot enrol in a finished masterclass', function () {
    $user = userWithProfile();
    $class = makeMasterclass(['starts_at' => now()->subWeeks(4), 'ends_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")
        ->assertStatus(422);
});

test('enrolling twice is idempotent', function () {
    $user = userWithProfile();
    $class = makeMasterclass();

    $this->actingAs($user)->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")->assertCreated();
    $this->actingAs($user)->postJson("/api/v1/masterclasses/{$class->ulid}/enrol")->assertCreated();

    expect($class->participants()->count())->toBe(1);
});

test('masterclasses require authentication', function () {
    $this->getJson('/api/v1/masterclasses')->assertUnauthorized();
});

test('the masterclass seeder is idempotent', function () {
    $this->seed(MasterclassSeeder::class);
    $count = Masterclass::query()->count();
    $this->seed(MasterclassSeeder::class);

    expect(Masterclass::query()->count())->toBe($count)->toBeGreaterThan(0);
});
