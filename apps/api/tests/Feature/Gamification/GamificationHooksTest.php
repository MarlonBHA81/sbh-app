<?php

use App\Models\Follow;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\XpLedgerEntry;
use App\Services\Gamification\GamificationService as G;
use Database\Seeders\XpActionSeeder;

/**
 * Seed the real XP actions so hook wiring can award through them.
 */
function seedXpActions(): void
{
    (new XpActionSeeder)->run();
}

function xpOf(Profile $profile): int
{
    return (int) $profile->fresh()->xp_total;
}

beforeEach(function () {
    seedXpActions();
});

test('publishing a post awards the author', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'text',
        'body' => 'Hello world',
    ])->assertCreated();

    expect(xpOf($user->personalProfile))->toBe(10)
        ->and(XpLedgerEntry::where('action_key', G::POST_PUBLISHED)->count())->toBe(1);
});

test('commenting awards the comment author', function () {
    $author = userWithProfile();
    $commenter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", [
        'body' => 'Nice post',
    ])->assertCreated();

    expect(xpOf($commenter->personalProfile))->toBe(5);
});

test('a like awards the post author but not the liker', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    expect(xpOf($author->personalProfile))->toBe(2)
        ->and(xpOf($liker->personalProfile))->toBe(0);
});

test('liking your own post awards no XP', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    expect(xpOf($author->personalProfile))->toBe(0)
        ->and(XpLedgerEntry::where('action_key', G::LIKE_RECEIVED)->count())->toBe(0);
});

test('an upvote awards the post author', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 1])->assertOk();

    expect(xpOf($author->personalProfile))->toBe(2)
        ->and(XpLedgerEntry::where('action_key', G::UPVOTE_RECEIVED)->count())->toBe(1);
});

test('following a public profile awards the followed profile', function () {
    $follower = userWithProfile(['handle' => 'follower']);
    $target = userWithProfile(['handle' => 'target']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/target/follow')->assertCreated();

    expect(xpOf($target->personalProfile))->toBe(3)
        ->and(xpOf($follower->personalProfile))->toBe(0);
});

test('accepting a follow request awards the followed profile', function () {
    $follower = userWithProfile(['handle' => 'follower']);
    $target = userWithProfile(['handle' => 'target', 'is_private' => true]);

    $follow = Follow::create([
        'follower_profile_id' => $follower->personalProfile->id,
        'followed_profile_id' => $target->personalProfile->id,
        'state' => Follow::STATE_PENDING,
    ]);

    $this->actingAs($target)->postJson("/api/v1/me/follow-requests/{$follow->id}/accept")->assertOk();

    expect(xpOf($target->personalProfile))->toBe(3);
});

test('casting a poll vote awards the voter', function () {
    $author = userWithProfile();
    $voter = userWithProfile();

    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'type' => 'poll']);
    $poll = Poll::create(['post_id' => $post->id]);
    $option = PollOption::create(['poll_id' => $poll->id, 'label' => 'A', 'position' => 0]);

    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/poll-vote", [
        'option_id' => $option->id,
    ])->assertOk();

    expect(xpOf($voter->personalProfile))->toBe(1);
});

test('completing a quiz awards the attempter', function () {
    $author = userWithProfile();
    $taker = userWithProfile();

    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'type' => 'quiz']);
    $quiz = Quiz::create(['post_id' => $post->id]);
    QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question' => 'Q1',
        'options' => ['a', 'b'],
        'correct_index' => 0,
        'position' => 0,
    ]);

    $this->actingAs($taker)->postJson("/api/v1/posts/{$post->ulid}/quiz-attempt", [
        'answers' => [0],
    ])->assertOk();

    expect(xpOf($taker->personalProfile))->toBe(3);
});
