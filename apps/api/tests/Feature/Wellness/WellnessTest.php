<?php

use App\Models\WellnessCheckin;
use App\Models\WellnessResource;

test('the resources list shows only published wellness resources', function () {
    $user = userWithProfile();

    WellnessResource::create([
        'category' => 'rest',
        'title' => 'Published one',
        'body' => 'Rest is part of the work.',
        'is_published' => true,
    ]);
    WellnessResource::create([
        'category' => 'rest',
        'title' => 'Draft one',
        'body' => 'Not visible yet.',
        'is_published' => false,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/wellness/resources')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Published one');
});

test('a member can log a private check-in and see only their own', function () {
    $me = userWithProfile();
    $other = userWithProfile();

    WellnessCheckin::create([
        'profile_id' => $other->profiles()->first()->id,
        'mood' => 2,
        'note' => 'Their private note',
    ]);

    $this->actingAs($me)
        ->postJson('/api/v1/me/wellness/checkins', ['mood' => 4, 'note' => 'Doing okay'])
        ->assertCreated()
        ->assertJsonPath('data.mood', 4)
        ->assertJsonPath('data.note', 'Doing okay');

    $this->actingAs($me)
        ->getJson('/api/v1/me/wellness/checkins')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mood', 4);
});

test('a check-in mood must be within range', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/wellness/checkins', ['mood' => 9])
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson('/api/v1/me/wellness/checkins', ['mood' => 0])
        ->assertStatus(422);
});

test('a check-in note is optional', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/wellness/checkins', ['mood' => 3])
        ->assertCreated()
        ->assertJsonPath('data.note', null);
});

test('wellness endpoints require authentication', function () {
    $this->getJson('/api/v1/wellness/resources')->assertUnauthorized();
    $this->getJson('/api/v1/me/wellness/checkins')->assertUnauthorized();
    $this->postJson('/api/v1/me/wellness/checkins', ['mood' => 3])->assertUnauthorized();
});

test('the wellness seeder is idempotent', function () {
    (new Database\Seeders\WellnessResourceSeeder())->run();
    (new Database\Seeders\WellnessResourceSeeder())->run();

    expect(WellnessResource::query()->count())->toBe(4);
});
