<?php

use App\Models\Challenge;
use App\Models\XpLedgerEntry;

function makeChallenge(array $attributes = []): Challenge
{
    return Challenge::create(array_merge([
        'title' => 'July Hustle',
        'description' => 'Most XP this month wins.',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(7),
        'is_active' => true,
    ], $attributes));
}

function grantXp(int $profileId, int $points, $at): void
{
    XpLedgerEntry::forceCreate([
        'profile_id' => $profileId,
        'action_key' => 'post_create',
        'points' => $points,
        'created_at' => $at,
    ]);
}

test('a member can join a challenge and appear on its leaderboard', function () {
    $user = userWithProfile();
    $challenge = makeChallenge();

    $this->actingAs($user)
        ->postJson("/api/v1/challenges/{$challenge->ulid}/join")
        ->assertCreated();

    grantXp($user->personalProfile->id, 30, now());

    $this->actingAs($user)
        ->getJson("/api/v1/challenges/{$challenge->ulid}")
        ->assertOk()
        ->assertJsonPath('data.joined', true)
        ->assertJsonPath('data.entries.0.profile.handle', $user->personalProfile->handle)
        ->assertJsonPath('data.entries.0.xp', 30)
        ->assertJsonPath('data.me.position', 1);
});

test('only XP earned inside the challenge window counts', function () {
    $user = userWithProfile();
    $challenge = makeChallenge();
    $challenge->participants()->attach($user->personalProfile->id, ['joined_at' => now()]);

    grantXp($user->personalProfile->id, 100, now()->subDays(3)); // before window
    grantXp($user->personalProfile->id, 25, now());              // inside

    $this->actingAs($user)
        ->getJson("/api/v1/challenges/{$challenge->ulid}")
        ->assertOk()
        ->assertJsonPath('data.entries.0.xp', 25);
});

test('non-participants earn XP but stay off the challenge board', function () {
    $joined = userWithProfile();
    $bystander = userWithProfile();
    $challenge = makeChallenge();
    $challenge->participants()->attach($joined->personalProfile->id, ['joined_at' => now()]);

    grantXp($bystander->personalProfile->id, 500, now());

    $response = $this->actingAs($joined)
        ->getJson("/api/v1/challenges/{$challenge->ulid}")
        ->assertOk();

    expect(collect($response->json('data.entries'))->pluck('profile.handle'))
        ->toContain($joined->personalProfile->handle)
        ->not->toContain($bystander->personalProfile->handle);
});

test('an ended challenge cannot be joined', function () {
    $user = userWithProfile();
    $challenge = makeChallenge([
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/challenges/{$challenge->ulid}/join")
        ->assertStatus(422);
});

test('an inactive challenge is hidden from the app', function () {
    $user = userWithProfile();
    $challenge = makeChallenge(['is_active' => false]);

    $this->actingAs($user)
        ->getJson("/api/v1/challenges/{$challenge->ulid}")
        ->assertNotFound();

    $list = $this->actingAs($user)->getJson('/api/v1/challenges')->assertOk();
    expect(collect($list->json('data'))->pluck('ulid'))->not->toContain($challenge->ulid);
});

test('leaving a challenge removes the viewer row', function () {
    $user = userWithProfile();
    $challenge = makeChallenge();
    $challenge->participants()->attach($user->personalProfile->id, ['joined_at' => now()]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/challenges/{$challenge->ulid}/join")
        ->assertNoContent();

    $this->actingAs($user)
        ->getJson("/api/v1/challenges/{$challenge->ulid}")
        ->assertOk()
        ->assertJsonPath('data.joined', false)
        ->assertJsonPath('data.me', null);
});
