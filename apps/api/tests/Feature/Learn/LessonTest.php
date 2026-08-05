<?php

use App\Models\Lesson;
use App\Models\LessonTrack;
use App\Models\Profile;
use App\Models\XpLedgerEntry;
use Database\Seeders\XpActionSeeder;

function makeLesson(array $attributes = []): Lesson
{
    return Lesson::create(array_merge([
        'title' => 'Why register your business',
        'body' => 'Registering makes your business a real, separate entity.',
        'minutes' => 4,
        'is_published' => true,
    ], $attributes));
}

test('the learn list returns only published lessons', function () {
    $user = userWithProfile();

    makeLesson(['title' => 'Live lesson']);
    makeLesson(['title' => 'Draft lesson', 'is_published' => false]);

    $this->actingAs($user)
        ->getJson('/api/v1/learn/lessons')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Live lesson')
        ->assertJsonPath('data.0.is_completed', false);
});

test('the learn list filters by journey stage', function () {
    $user = userWithProfile();

    makeLesson(['title' => 'Starting lesson', 'journey_stage' => 'starting']);
    makeLesson(['title' => 'Hiring lesson', 'journey_stage' => 'hiring']);

    $this->actingAs($user)
        ->getJson('/api/v1/learn/lessons?journey_stage=hiring')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Hiring lesson');
});

test('completing a lesson awards XP once and tracks progress', function () {
    $this->seed(XpActionSeeder::class);

    $user = userWithProfile();
    $profile = $user->profiles()->first();
    $lesson = makeLesson();

    $this->actingAs($user)
        ->postJson("/api/v1/learn/lessons/{$lesson->ulid}/complete")
        ->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.progress.completed', 1)
        ->assertJsonPath('data.progress.total', 1);

    // Re-completing is idempotent — no second XP award.
    $this->actingAs($user)
        ->postJson("/api/v1/learn/lessons/{$lesson->ulid}/complete")
        ->assertOk();

    $awards = XpLedgerEntry::query()
        ->where('profile_id', $profile->id)
        ->where('action_key', 'lesson_completed')
        ->count();

    expect($awards)->toBe(1);
    expect(Profile::find($profile->id)->xp_total)->toBe(10);
});

test('the lesson list reflects the viewer completion state', function () {
    $this->seed(XpActionSeeder::class);

    $user = userWithProfile();
    $lesson = makeLesson();

    $this->actingAs($user)->postJson("/api/v1/learn/lessons/{$lesson->ulid}/complete")->assertOk();

    $this->actingAs($user)
        ->getJson('/api/v1/learn/lessons')
        ->assertJsonPath('data.0.is_completed', true);
});

test('a lesson show returns the next lesson in its track', function () {
    $user = userWithProfile();

    $track = LessonTrack::create(['title' => 'Foundations', 'is_published' => true]);
    $first = makeLesson(['title' => 'First', 'lesson_track_id' => $track->id, 'position' => 1]);
    $second = makeLesson(['title' => 'Second', 'lesson_track_id' => $track->id, 'position' => 2]);

    $this->actingAs($user)
        ->getJson("/api/v1/learn/lessons/{$first->ulid}")
        ->assertOk()
        ->assertJsonPath('data.title', 'First')
        ->assertJsonPath('next.ulid', $second->ulid);
});

test('a draft lesson cannot be viewed or completed', function () {
    $user = userWithProfile();
    $draft = makeLesson(['is_published' => false]);

    $this->actingAs($user)->getJson("/api/v1/learn/lessons/{$draft->ulid}")->assertNotFound();
    $this->actingAs($user)->postJson("/api/v1/learn/lessons/{$draft->ulid}/complete")->assertNotFound();
});

test('lessons require authentication', function () {
    makeLesson();

    $this->getJson('/api/v1/learn/lessons')->assertUnauthorized();
});
