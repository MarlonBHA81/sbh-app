<?php

use App\Models\AdEvent;
use App\Models\Masterclass;

function makeRoom(array $attributes = []): Masterclass
{
    return Masterclass::create(array_merge([
        'title' => 'Growth room',
        'description' => 'A branded room.',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(2),
        'is_published' => true,
    ], $attributes));
}

test('sponsored rooms lead the list and expose branding + sponsor fields', function () {
    $user = userWithProfile();

    makeRoom(['title' => 'Organic soonest', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    makeRoom([
        'title' => 'Sponsored later',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHour(),
        'is_sponsored' => true,
        'sponsor_name' => 'Acme Bank',
        'sponsor_url' => 'https://acme.test',
        'brand_color' => '#123456',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/masterclasses')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Sponsored later')
        ->assertJsonPath('data.0.is_sponsored', true)
        ->assertJsonPath('data.0.sponsor_name', 'Acme Bank')
        ->assertJsonPath('data.0.brand_color', '#123456')
        ->assertJsonPath('data.1.title', 'Organic soonest')
        ->assertJsonPath('data.1.is_sponsored', false);
});

test('a sponsored room impression and click are recorded in ad_events', function () {
    $user = userWithProfile();
    $room = makeRoom(['is_sponsored' => true, 'sponsor_name' => 'Acme']);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'room_ulid' => $room->ulid])
        ->assertNoContent();

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'click', 'room_ulid' => $room->ulid])
        ->assertNoContent();

    expect(AdEvent::where('masterclass_id', $room->id)->where('kind', 'impression')->count())->toBe(1)
        ->and(AdEvent::where('masterclass_id', $room->id)->where('kind', 'click')->count())->toBe(1);
});

test('a non-sponsored room records no ad events', function () {
    $user = userWithProfile();
    $room = makeRoom(['is_sponsored' => false]);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'room_ulid' => $room->ulid])
        ->assertNoContent();

    expect(AdEvent::where('masterclass_id', $room->id)->count())->toBe(0);
});

test('sponsored room impressions are deduped within the window', function () {
    $user = userWithProfile();
    $room = makeRoom(['is_sponsored' => true]);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)
            ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'room_ulid' => $room->ulid])
            ->assertNoContent();
    }

    expect(AdEvent::where('masterclass_id', $room->id)->where('kind', 'impression')->count())->toBe(1);
});

test('a room track target cannot be combined with another target', function () {
    $user = userWithProfile();
    $room = makeRoom(['is_sponsored' => true]);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', [
            'kind' => 'impression',
            'room_ulid' => $room->ulid,
            'slot_key' => 'right_rail_default',
        ])
        ->assertStatus(422);
});
