<?php

use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Services\Business\MatchmakingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function seedDemo(array $options = []): void
{
    Artisan::call('demo:seed', array_merge(['--force' => true], $options));
}

test('demo:seed creates the demo users, profiles and follow graph', function () {
    seedDemo();

    $demoUsers = User::query()->where('email', 'like', '%@demo.sbh')->get();

    expect($demoUsers->count())->toBeGreaterThanOrEqual(12)
        ->and(User::query()->where('email', 'not like', '%@demo.sbh')->count())->toBe(0)
        ->and(Profile::query()->business()->count())->toBeGreaterThanOrEqual(6)
        ->and(Profile::query()->where('is_private', true)->count())->toBeGreaterThanOrEqual(2)
        ->and(Profile::query()->whereNotNull('lat')->where('share_location', true)->count())->toBeGreaterThanOrEqual(5)
        ->and(Profile::query()->distinct()->pluck('city')->filter()->sort()->values()->all())
        ->toContain('Johannesburg', 'Cape Town', 'Durban');

    // Accepted follows plus a pending request to a private profile.
    expect(Follow::query()->where('state', Follow::STATE_ACCEPTED)->count())->toBeGreaterThan(20)
        ->and(Follow::query()->where('state', Follow::STATE_PENDING)->count())->toBeGreaterThanOrEqual(1);

    // Follower counters were maintained by the service.
    expect(Profile::query()->where('followers_count', '>', 0)->count())->toBeGreaterThan(5);

    // Topic follows exist.
    expect(DB::table('topic_follows')->count())->toBeGreaterThan(10);
});

test('demo:seed creates every post type with working satellites', function () {
    seedDemo();

    $types = Post::query()->distinct()->pluck('type');

    expect($types->count())->toBeGreaterThanOrEqual(15)
        ->and($types->all())->toContain(
            'text', 'link', 'image', 'quote', 'repost', 'typewriter', 'magnifier',
            'secret', 'checkin', 'blog', 'poll', 'quiz', 'event', 'job', 'portfolio', 'audio',
        );

    // Draft + scheduled + sensitive + followers-only variants exist.
    expect(Post::query()->where('status', Post::STATUS_DRAFT)->count())->toBeGreaterThanOrEqual(1)
        ->and(Post::query()->where('status', Post::STATUS_SCHEDULED)->count())->toBeGreaterThanOrEqual(1)
        ->and(Post::query()->where('sensitive', true)->count())->toBeGreaterThanOrEqual(1)
        ->and(Post::query()->where('visibility', Post::VISIBILITY_FOLLOWERS)->count())->toBeGreaterThanOrEqual(1);

    // Poll votes from several profiles, and one poll has ended.
    expect(DB::table('poll_votes')->count())->toBeGreaterThanOrEqual(10)
        ->and(DB::table('polls')->where('ends_at', '<', now())->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('polls')->where('ends_at', '>', now())->count())->toBeGreaterThanOrEqual(1);

    // Quiz attempts and event RSVPs.
    expect(DB::table('quiz_attempts')->count())->toBeGreaterThanOrEqual(3)
        ->and(DB::table('event_rsvps')->count())->toBeGreaterThanOrEqual(5)
        ->and(DB::table('events')->where('starts_at', '>', now())->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('events')->where('starts_at', '<', now())->count())->toBeGreaterThanOrEqual(1);

    // Jobs: one open, one expired.
    expect(DB::table('job_listings')->where('expires_at', '>', now())->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('job_listings')->where('expires_at', '<', now())->count())->toBeGreaterThanOrEqual(1);

    // Generated media landed on disk (images with thumbs, plus real audio).
    $image = Media::query()->where('type', Media::TYPE_IMAGE)->firstOrFail();
    $audio = Media::query()->where('type', Media::TYPE_AUDIO)->firstOrFail();

    Storage::disk('public')->assertExists($image->path);
    Storage::disk('public')->assertExists($image->thumb_path);
    Storage::disk('public')->assertExists($audio->path);

    expect($audio->status)->toBe(Media::STATUS_READY)
        ->and(Media::query()->count())->toBeGreaterThanOrEqual(7);
});

test('demo:seed creates engagement, messaging, XP history and notifications', function () {
    seedDemo();

    // Threaded comments reach depth 2 and carry likes.
    expect(DB::table('comments')->count())->toBeGreaterThanOrEqual(8)
        ->and(DB::table('comments')->where('depth', 2)->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('comments')->where('likes_count', '>', 0)->count())->toBeGreaterThanOrEqual(2);

    // Reactions spread across posts (likes + votes).
    expect(DB::table('reactions')->where('bucket', 'like')->count())->toBeGreaterThan(20)
        ->and(DB::table('reactions')->where('bucket', 'vote')->count())->toBeGreaterThanOrEqual(4);

    // Mentions were parsed out of post/comment bodies.
    expect(DB::table('mentions')->count())->toBeGreaterThanOrEqual(3);

    // Messaging: 2 DMs + 1 group with messages, reactions and read states.
    expect(Conversation::query()->where('kind', Conversation::KIND_DM)->count())->toBe(2)
        ->and(Conversation::query()->where('kind', Conversation::KIND_GROUP)->count())->toBe(1)
        ->and(Conversation::query()->where('kind', Conversation::KIND_GROUP)->first()->title)->toBe('Joburg Traders')
        ->and(Conversation::query()->where('kind', Conversation::KIND_GROUP)->first()->rules)->not->toBeNull()
        ->and(DB::table('messages')->count())->toBeGreaterThanOrEqual(10)
        ->and(DB::table('message_reactions')->count())->toBeGreaterThanOrEqual(5)
        ->and(DB::table('conversation_participants')->whereNotNull('last_read_message_id')->count())->toBeGreaterThanOrEqual(4);

    // XP: live awards + 3 weeks of backfilled history, totals in line.
    expect(DB::table('xp_ledger')->count())->toBeGreaterThan(100)
        ->and(DB::table('xp_ledger')->where('created_at', '<', now()->subDays(7))->count())->toBeGreaterThan(10)
        ->and(Profile::query()->where('xp_total', '>', 0)->count())->toBeGreaterThan(5)
        ->and(Profile::query()->whereNotNull('rank_id')->count())->toBeGreaterThan(5);

    // Real notifications were sent by the services.
    expect(DB::table('notifications')->count())->toBeGreaterThan(50);
});

test('demo:seed creates business, ads, analytics and moderation data', function () {
    seedDemo();

    // Business needs are matchable: the braai business finds a reciprocal match.
    expect(DB::table('business_needs')->where('active', true)->count())->toBeGreaterThanOrEqual(6);

    $braai = Profile::query()->where('handle', 'braai_bros')->firstOrFail();
    $matches = app(MatchmakingService::class)->matches($braai);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0]['score'])->toBeGreaterThan(0);

    // Campaigns: one active + one completed, with events across 14 days.
    expect(Campaign::query()->where('status', Campaign::STATUS_ACTIVE)->count())->toBe(1)
        ->and(Campaign::query()->where('status', Campaign::STATUS_COMPLETED)->count())->toBe(1)
        ->and(DB::table('ad_events')->count())->toBeGreaterThan(50)
        ->and(DB::table('ad_events')->where('created_at', '<', now()->subDays(7))->count())->toBeGreaterThan(10)
        ->and(DB::table('ad_events')->where('kind', 'click')->count())->toBeGreaterThan(0);

    // One active slot per placement with a generated creative.
    $slots = DB::table('ad_slots')->where('active', true)->get();
    expect($slots->pluck('placement')->sort()->values()->all())->toBe(['feed_inline', 'right_rail']);
    foreach ($slots as $slot) {
        Storage::disk('public')->assertExists($slot->image_path);
    }

    // Analytics backfill across the last 30 days for many posts.
    expect(DB::table('post_stats_daily')->count())->toBeGreaterThan(100)
        ->and(DB::table('post_stats_daily')->distinct()->count('post_id'))->toBeGreaterThanOrEqual(15)
        ->and(DB::table('post_stats_daily')->where('date', '<', now()->subDays(7)->toDateString())->count())->toBeGreaterThan(20);

    // Two pending reports: one against a post, one against a profile.
    expect(Report::query()->where('status', Report::STATUS_PENDING)->count())->toBe(2)
        ->and(Report::query()->pluck('reportable_type')->sort()->values()->all())
        ->toBe([Post::class, Profile::class]);
});

test('demo:seed --fresh reseeds without duplicating demo users', function () {
    seedDemo();

    $firstCount = User::query()->where('email', 'like', '%@demo.sbh')->count();
    $firstPostCount = Post::query()->count();

    seedDemo(['--fresh' => true]);

    expect(User::query()->where('email', 'like', '%@demo.sbh')->count())->toBe($firstCount)
        ->and(Post::query()->count())->toBe($firstPostCount)
        ->and(DB::table('notifications')->count())->toBeGreaterThan(0);

    // Topic counters were recomputed, not doubled.
    $tagged = DB::table('post_topic')->count();
    expect((int) DB::table('topics')->sum('posts_count'))->toBe($tagged);
});

test('demo:seed without --fresh or --force aborts when demo data exists', function () {
    seedDemo();

    $count = User::query()->count();

    // Non-interactive confirm() answers the default (no) and the run aborts.
    Artisan::call('demo:seed');

    expect(User::query()->count())->toBe($count);
});
