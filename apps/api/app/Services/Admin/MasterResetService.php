<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes ALL user-generated content and returns the database to a pristine,
 * demo-free state while preserving the platform's reference data and the
 * administrator accounts that operate it.
 *
 * What is deleted:
 *  - Every non-admin user (and their profiles) plus every piece of content
 *    authored by ANY profile — including admin-owned posts/comments/messages.
 *    A reset means a genuine clean slate, not merely "everything but mine".
 *  - All media files on disk (media/ + chunks/ on both the public and local
 *    disks) and all cached derived state (seen-sets, leaderboards, matches).
 *
 * What is kept:
 *  - admin / super-admin user rows and their profiles (counters zeroed,
 *    xp_total reset to 0 and rank cleared to the default);
 *  - topics (posts_count/followers_count recomputed to 0 after their pivots
 *    are wiped), badges, ranks, xp_actions, business_categories, settings and
 *    ad_slots.
 *
 * The wholesale content wipe is safe because, once every non-admin user is
 * gone, the only rows left in the content tables belong to admins (or are
 * orphaned morphs), all of which the reset is meant to remove.
 */
class MasterResetService
{
    /**
     * User-generated content tables truncated wholesale during a reset. Order
     * is irrelevant because foreign-key checks are disabled for the wipe.
     *
     * @var list<string>
     */
    private const CONTENT_TABLES = [
        // Posts and their satellites / pivots.
        'post_stats_daily',
        'post_topic',
        'poll_votes',
        'poll_options',
        'polls',
        'quiz_attempts',
        'quiz_questions',
        'quizzes',
        'event_rsvps',
        'events',
        'job_listings',
        // Engagement.
        'reactions',
        'comments',
        'mentions',
        'posts',
        // Media.
        'media',
        'upload_sessions',
        // Social graph.
        'challenge_participants',
        'follows',
        'topic_follows',
        'blocks',
        'mutes',
        // Messaging.
        'message_reactions',
        'messages',
        'conversation_participants',
        'conversations',
        // Business.
        'business_needs',
        // Ads (ad_slots are reference data and intentionally kept).
        'ad_events',
        'campaigns',
        // Gamification history.
        'xp_ledger',
        'profile_badges',
        // Moderation & notifications.
        'reports',
        'moderation_actions',
        'notifications',
        'push_subscriptions',
    ];

    /**
     * Run the reset. Returns a per-table map of the number of rows deleted,
     * keyed by table name, plus an entry for each retained-but-reset table.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        $counts = [];

        $adminUserIds = User::query()->where('is_admin', true)->pluck('id')->all();

        // 1. Wipe every content table wholesale. Foreign-key checks are turned
        //    off so the tables can be cleared in any order without violating
        //    referential integrity mid-flight.
        Schema::withoutForeignKeyConstraints(function () use (&$counts) {
            foreach (self::CONTENT_TABLES as $table) {
                $counts[$table] = DB::table($table)->delete();
            }
        });

        // 2. Remove every non-admin user. Profiles and social accounts cascade
        //    via their foreign keys (constraints are back on here). API tokens
        //    are a morph without an FK, so non-admin tokens go explicitly.
        $counts['profiles'] = DB::table('profiles')
            ->whereNotIn('user_id', $adminUserIds ?: [0])
            ->count();

        $counts['personal_access_tokens'] = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereNotIn('tokenable_id', $adminUserIds ?: [0])
            ->delete();

        $counts['users'] = DB::table('users')
            ->whereNotIn('id', $adminUserIds ?: [0])
            ->delete();

        // 3. Reset the retained admin profiles to a pristine state.
        DB::table('profiles')->update([
            'followers_count' => 0,
            'following_count' => 0,
            'posts_count' => 0,
            'xp_total' => 0,
            'rank_id' => null,
        ]);

        // 4. Reset topic aggregates (their pivots were wiped in step 1).
        DB::table('topics')->update([
            'posts_count' => 0,
            'followers_count' => 0,
        ]);

        // 5. Purge media files and chunk directories from disk.
        $this->clearStorage();

        // 6. Flush all cached derived state (seen-sets, leaderboards, matches).
        Cache::flush();

        return $counts;
    }

    /**
     * Delete only the demo dataset: every user whose email ends in the
     * reserved demo domain (demo.sbh — see DemoContentSeeder), cascading their
     * profiles and all authored content. Used by `demo:seed --fresh` for an
     * idempotent reseed. Returns deleted counts.
     *
     * @return array{users: int, media_dirs: int}
     */
    public function deleteDemoUsers(): array
    {
        $demoUsers = User::query()
            ->where('email', 'like', '%@demo.sbh')
            ->with('profiles:id,ulid,user_id')
            ->get();

        $profileUlids = $demoUsers
            ->flatMap(fn (User $user) => $user->profiles->pluck('ulid'))
            ->filter()
            ->values()
            ->all();

        // Notifications are a morph without an FK, so they don't cascade with
        // the user rows and must be removed explicitly.
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $demoUsers->pluck('id'))
            ->delete();

        // Cascades handle profiles → posts/media/follows/etc. at the FK level.
        $demoUsers->each->delete();

        // Demo posts and topic follows are gone; recompute topic aggregates
        // from the surviving pivot rows so counters stay truthful.
        $this->recomputeTopicCounters();

        // Remove the demo profiles' media directories from disk.
        foreach ($profileUlids as $ulid) {
            foreach (['public', 'local'] as $disk) {
                Storage::disk($disk)->deleteDirectory('media/'.$ulid);
            }
        }

        Cache::flush();

        return ['users' => $demoUsers->count(), 'media_dirs' => count($profileUlids)];
    }

    /**
     * Recompute topics.posts_count / followers_count from their pivot tables.
     */
    private function recomputeTopicCounters(): void
    {
        DB::table('topics')->update([
            'posts_count' => DB::raw('(select count(*) from post_topic where post_topic.topic_id = topics.id)'),
            'followers_count' => DB::raw('(select count(*) from topic_follows where topic_follows.topic_id = topics.id)'),
        ]);
    }

    /**
     * Remove all media and chunk directories from every disk they may live on.
     */
    private function clearStorage(): void
    {
        foreach (['public', 'local'] as $disk) {
            Storage::disk($disk)->deleteDirectory('media');
            Storage::disk($disk)->deleteDirectory('chunks');
        }
    }
}
