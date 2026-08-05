<?php

use App\Models\Challenge;

function validChallengePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Post every day',
        'description' => 'Share one update a day this week.',
        'starts_at' => now()->toISOString(),
        'ends_at' => now()->addWeek()->toISOString(),
    ], $overrides);
}

test('a facilitator can create a challenge', function () {
    $user = userWithProfile(['is_facilitator' => true]);

    $this->actingAs($user)
        ->postJson('/api/v1/challenges', validChallengePayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Post every day');

    expect(Challenge::first()->created_by_profile_id)
        ->toBe($user->profiles()->first()->id);
});

test('a non-facilitator member cannot create a challenge', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/challenges', validChallengePayload())
        ->assertForbidden();
});

test('an admin can create a challenge', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)
        ->postJson('/api/v1/challenges', validChallengePayload())
        ->assertCreated();
});

test('challenge creation validates that it ends after it starts', function () {
    $user = userWithProfile(['is_facilitator' => true]);

    $this->actingAs($user)
        ->postJson('/api/v1/challenges', validChallengePayload([
            'ends_at' => now()->subDay()->toISOString(),
        ]))
        ->assertStatus(422);
});

test('a facilitator can update their own challenge but not another facilitator\'s', function () {
    $a = userWithProfile(['is_facilitator' => true]);
    $b = userWithProfile(['is_facilitator' => true]);

    $challenge = Challenge::create([
        'title' => 'Original',
        'description' => 'Body',
        'starts_at' => now(),
        'ends_at' => now()->addWeek(),
        'is_active' => true,
        'created_by_profile_id' => $a->profiles()->first()->id,
    ]);

    $this->actingAs($a)
        ->patchJson("/api/v1/challenges/{$challenge->ulid}", ['title' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');

    $this->actingAs($b)
        ->patchJson("/api/v1/challenges/{$challenge->ulid}", ['title' => 'Hijack'])
        ->assertForbidden();
});

test('an admin can update any challenge', function () {
    $facilitator = userWithProfile(['is_facilitator' => true]);
    $admin = adminWithProfile();

    $challenge = Challenge::create([
        'title' => 'Original',
        'description' => 'Body',
        'starts_at' => now(),
        'ends_at' => now()->addWeek(),
        'is_active' => true,
        'created_by_profile_id' => $facilitator->profiles()->first()->id,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/challenges/{$challenge->ulid}", ['is_active' => false])
        ->assertOk();

    expect($challenge->fresh()->is_active)->toBeFalse();
});
