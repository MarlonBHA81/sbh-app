<?php

use App\Models\Masterclass;
use App\Models\User;

/** A user whose personal profile is a facilitator. */
function facilitator(): User
{
    $user = userWithProfile();
    $user->personalProfile->forceFill(['is_facilitator' => true])->save();

    return $user;
}

test('a facilitator can create their own masterclass', function () {
    $user = facilitator();

    $res = $this->actingAs($user)
        ->postJson('/api/v1/me/masterclasses', [
            'title' => 'Founder Sales Sprint',
            'description' => 'A four-week cohort.',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeeks(5)->toIso8601String(),
            'capacity' => 25,
            'is_published' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Founder Sales Sprint');

    $class = Masterclass::sole();
    expect($class->created_by)->toBe($user->id)
        ->and($class->facilitator_name)->toBe($user->personalProfile->name);
});

test('a non-facilitator cannot create a masterclass', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/masterclasses', [
            'title' => 'Nope',
            'description' => 'x',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeeks(2)->toIso8601String(),
        ])
        ->assertForbidden();
});

test('a facilitator only sees and manages their own masterclasses', function () {
    $mine = facilitator();
    $other = facilitator();

    $ownClass = Masterclass::create([
        'title' => 'Mine', 'description' => 'x',
        'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeeks(3),
        'created_by' => $mine->id, 'is_published' => true,
    ]);
    $otherClass = Masterclass::create([
        'title' => 'Theirs', 'description' => 'x',
        'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeeks(3),
        'created_by' => $other->id, 'is_published' => true,
    ]);

    $res = $this->actingAs($mine)->getJson('/api/v1/me/masterclasses')->assertOk();
    expect(collect($res->json('data'))->pluck('ulid'))
        ->toContain($ownClass->ulid)
        ->not->toContain($otherClass->ulid);

    // Can't edit someone else's.
    $this->actingAs($mine)
        ->patchJson("/api/v1/me/masterclasses/{$otherClass->ulid}", ['title' => 'Hijack'])
        ->assertForbidden();

    // Can edit + delete own.
    $this->actingAs($mine)
        ->patchJson("/api/v1/me/masterclasses/{$ownClass->ulid}", ['title' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed');

    $this->actingAs($mine)
        ->deleteJson("/api/v1/me/masterclasses/{$ownClass->ulid}")
        ->assertNoContent();
});

test('the creator is treated as host on the live panel', function () {
    $user = facilitator();
    $class = Masterclass::create([
        'title' => 'Live one', 'description' => 'x',
        'starts_at' => now()->subHour(), 'ends_at' => now()->addWeek(),
        'created_by' => $user->id, 'is_published' => true,
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/masterclasses/{$class->ulid}/live")
        ->assertOk()
        ->assertJsonPath('data.is_host', true);
});
