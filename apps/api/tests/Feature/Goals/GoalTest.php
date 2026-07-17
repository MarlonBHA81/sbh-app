<?php

use App\Models\Goal;
use App\Models\Profile;
use App\Models\XpAction;
use App\Services\Gamification\GamificationService;
use Database\Seeders\XpActionSeeder;

function activeProfileOf(\App\Models\User $user): Profile
{
    return $user->profiles()->first();
}

test('a member can create and list their own goals', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/goals', [
            'title' => 'Register for VAT',
            'target' => 'By end of quarter',
            'due_on' => now()->addMonth()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Register for VAT')
        ->assertJsonPath('data.is_done', false);

    $this->actingAs($user)
        ->getJson('/api/v1/me/goals')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Register for VAT');
});

test('a member never sees another member goals', function () {
    $me = userWithProfile();
    $other = userWithProfile();

    Goal::create(['profile_id' => activeProfileOf($other)->id, 'title' => 'Their goal']);

    $this->actingAs($me)
        ->getJson('/api/v1/me/goals')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('completing a goal awards XP once', function () {
    (new XpActionSeeder())->run();
    expect(XpAction::where('key', GamificationService::GOAL_COMPLETED)->exists())->toBeTrue();

    $user = userWithProfile();
    $goal = Goal::create([
        'profile_id' => activeProfileOf($user)->id,
        'title' => 'Launch online store',
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/goals/{$goal->ulid}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.is_done', true);

    $points = XpAction::where('key', GamificationService::GOAL_COMPLETED)->value('points');
    expect((int) activeProfileOf($user)->fresh()->xp_total)->toBe((int) $points);

    // Toggling done again is a no-op for XP (subject idempotency).
    $this->actingAs($user)
        ->patchJson("/api/v1/goals/{$goal->ulid}", ['is_done' => false]);
    $this->actingAs($user)
        ->patchJson("/api/v1/goals/{$goal->ulid}", ['is_done' => true]);

    expect((int) activeProfileOf($user)->fresh()->xp_total)->toBe((int) $points);
});

test('a member can edit and delete their goal', function () {
    $user = userWithProfile();
    $goal = Goal::create(['profile_id' => activeProfileOf($user)->id, 'title' => 'Old title']);

    $this->actingAs($user)
        ->patchJson("/api/v1/goals/{$goal->ulid}", ['title' => 'New title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New title');

    $this->actingAs($user)
        ->deleteJson("/api/v1/goals/{$goal->ulid}")
        ->assertNoContent();

    $this->assertSoftDeleted('goals', ['id' => $goal->id]);
});

test('a member cannot edit or delete a goal that is not theirs', function () {
    $me = userWithProfile();
    $other = userWithProfile();
    $goal = Goal::create(['profile_id' => activeProfileOf($other)->id, 'title' => 'Theirs']);

    $this->actingAs($me)
        ->patchJson("/api/v1/goals/{$goal->ulid}", ['title' => 'Hijack'])
        ->assertNotFound();

    $this->actingAs($me)
        ->deleteJson("/api/v1/goals/{$goal->ulid}")
        ->assertNotFound();
});

test('the dashboard returns growth stats and goals', function () {
    $user = userWithProfile(['helpful_count' => 4, 'xp_total' => 120]);
    $profile = activeProfileOf($user);

    Goal::create(['profile_id' => $profile->id, 'title' => 'Done one', 'is_done' => true, 'completed_at' => now()]);
    Goal::create(['profile_id' => $profile->id, 'title' => 'Open one']);

    $this->actingAs($user)
        ->getJson('/api/v1/me/dashboard')
        ->assertOk()
        ->assertJsonPath('data.stats.helpful_count', 4)
        ->assertJsonPath('data.stats.xp_total', 120)
        ->assertJsonPath('data.stats.goals_total', 2)
        ->assertJsonPath('data.stats.goals_completed', 1)
        ->assertJsonCount(2, 'data.goals');
});

test('goals require authentication', function () {
    $this->getJson('/api/v1/me/goals')->assertUnauthorized();
    $this->getJson('/api/v1/me/dashboard')->assertUnauthorized();
});
