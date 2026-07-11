<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Create one published post of every Milestone 5 type for the given author. */
function seedAllTypes(User $author): void
{
    $pid = $author->personalProfile->id;
    $video = Media::factory()->video()->create(['profile_id' => $pid]);
    $audio = Media::factory()->audio()->create(['profile_id' => $pid]);
    $images = Media::factory()->count(2)->create(['profile_id' => $pid]);

    $posts = [
        ['type' => 'video', 'body' => 'clip', 'media_ids' => [$video->ulid]],
        ['type' => 'audio', 'payload' => ['title' => 'ep'], 'media_ids' => [$audio->ulid]],
        ['type' => 'blog', 'payload' => ['title' => 'B', 'doc' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]],
        ['type' => 'poll', 'body' => 'Q?', 'payload' => ['options' => ['a', 'b']]],
        ['type' => 'quiz', 'payload' => ['questions' => [['question' => 'q', 'options' => ['a', 'b'], 'correct_index' => 0]]]],
        ['type' => 'event', 'payload' => ['title' => 'E', 'starts_at' => now()->addWeek()->toISOString()]],
        ['type' => 'job', 'payload' => ['title' => 'J', 'employment_type' => 'full_time']],
        ['type' => 'portfolio', 'payload' => ['title' => 'P'], 'media_ids' => $images->pluck('ulid')->all()],
    ];

    foreach ($posts as $body) {
        test()->actingAs($author)->postJson('/api/v1/posts', $body)->assertCreated();
    }
}

test('all eight new post types render with their satellite data', function () {
    $author = userWithProfile();
    seedAllTypes($author);

    $response = $this->actingAs($author)->getJson("/api/v1/profiles/{$author->personalProfile->handle}/posts")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type')->all();

    expect($types)->toContain('video', 'audio', 'blog', 'poll', 'quiz', 'event', 'job', 'portfolio');

    $byType = collect($response->json('data'))->keyBy('type');

    expect($byType['poll']['poll']['options'])->toHaveCount(2)
        ->and($byType['quiz']['quiz']['attempts_count'])->toBe(0)
        ->and($byType['event']['event']['title'])->toBe('E')
        ->and($byType['job']['job']['is_expired'])->toBeFalse()
        ->and($byType['video']['media'][0]['type'])->toBe('video');
});

test('a mixed-type feed resolves satellites without N+1 queries as it grows', function () {
    // Count queries that touch satellite tables; these must stay constant no
    // matter how many satellite-bearing posts are in the feed.
    $satelliteTables = ['polls', 'poll_options', 'poll_votes', 'quizzes',
        'quiz_questions', 'quiz_attempts', 'events', 'event_rsvps', 'job_listings'];

    $countSatelliteQueries = function () use ($satelliteTables) {
        return collect(DB::getQueryLog())
            ->filter(fn ($q) => collect($satelliteTables)->contains(
                fn ($t) => str_contains($q['query'], "\"{$t}\"")
            ))
            ->count();
    };

    $viewer = userWithProfile();

    $authorA = userWithProfile();
    seedAllTypes($authorA);
    acceptedFollow($viewer->personalProfile, $authorA->personalProfile);

    DB::enableQueryLog();
    $this->actingAs($viewer)->getJson('/api/v1/feeds/following')->assertOk();
    $baseline = $countSatelliteQueries();
    DB::flushQueryLog();

    // Double the feed contents with a second author's full set.
    $authorB = userWithProfile();
    seedAllTypes($authorB);
    acceptedFollow($viewer->personalProfile, $authorB->personalProfile);

    DB::flushQueryLog();
    $this->actingAs($viewer)->getJson('/api/v1/feeds/following')->assertOk();
    $doubled = $countSatelliteQueries();
    DB::disableQueryLog();

    // Eager loading + bulk viewer-state hydration means a fixed handful of
    // satellite queries regardless of how many such posts are present.
    expect($doubled)->toBe($baseline)
        ->and($baseline)->toBeLessThanOrEqual(count($satelliteTables));
});
