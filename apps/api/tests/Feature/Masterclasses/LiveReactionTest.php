<?php

use App\Events\LiveReaction;
use App\Models\Masterclass;
use Illuminate\Support\Facades\Event;

function reactionRoom(): Masterclass
{
    return Masterclass::create([
        'title' => 'React room',
        'description' => 'x',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'is_published' => true,
    ]);
}

test('an enrolled member can fire a live reaction', function () {
    $room = reactionRoom();
    $member = userWithProfile();
    $this->actingAs($member)->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")->assertCreated();

    Event::fake([LiveReaction::class]);

    $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live/react", ['emoji' => '👏'])
        ->assertNoContent();

    Event::assertDispatched(LiveReaction::class, fn (LiveReaction $e) => $e->emoji === '👏');
});

test('a non-participant cannot fire a live reaction', function () {
    $room = reactionRoom();
    // Create the room chat by enrolling one member.
    $this->actingAs(userWithProfile())->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")->assertCreated();

    Event::fake([LiveReaction::class]);

    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live/react", ['emoji' => '👏'])
        ->assertStatus(403);

    Event::assertNotDispatched(LiveReaction::class);
});

test('a reaction requires an emoji', function () {
    $room = reactionRoom();
    $member = userWithProfile();
    $this->actingAs($member)->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")->assertCreated();

    $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/live/react", [])
        ->assertStatus(422);
});
