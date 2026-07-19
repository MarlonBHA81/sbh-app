<?php

namespace App\Services\Gamification;

use App\Events\XpAwarded;
use App\Models\Profile;
use App\Models\Rank;
use App\Models\XpAction;
use App\Models\XpLedgerEntry;
use App\Notifications\RankUnlocked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Awards XP for user actions, maintains each profile's running total and
 * resolves rank progression.
 *
 * Design notes:
 * - Daily caps are counted over UTC calendar days. This is intentionally
 *   simpler than per-user-timezone windows and is accurate enough for a soft
 *   anti-farming guard.
 * - Reversals are NOT clawed back. Removing a like/upvote the same day leaves
 *   the awarded XP in place; the subject idempotency guard is what prevents
 *   unlike/relike farming (an award is only ever written once per subject).
 */
class GamificationService
{
    public const POST_PUBLISHED = 'post_published';

    public const COMMENT_CREATED = 'comment_created';

    public const LIKE_RECEIVED = 'like_received';

    public const UPVOTE_RECEIVED = 'upvote_received';

    public const FOLLOWER_GAINED = 'follower_gained';

    public const POLL_VOTE_CAST = 'poll_vote_cast';

    public const QUIZ_COMPLETED = 'quiz_completed';

    public const DAILY_ACTION_COMPLETED = 'daily_action_completed';

    public const HELPFUL_RECEIVED = 'helpful_received';

    public const LESSON_COMPLETED = 'lesson_completed';

    /**
     * Award XP to $profile for $actionKey, optionally bound to a subject.
     *
     * Returns the written ledger entry, or null when the award was a no-op
     * (unknown/disabled action, daily cap reached, or a duplicate for a
     * subject-bound action).
     */
    public function award(Profile $profile, string $actionKey, ?Model $subject = null): ?XpLedgerEntry
    {
        $action = XpAction::query()->where('key', $actionKey)->first();

        if ($action === null || ! $action->enabled) {
            return null;
        }

        // Subject idempotency: a given (profile, action, subject) may only ever
        // award once. Prevents unlike/relike (and re-follow) farming.
        if ($subject !== null && $this->subjectAlreadyAwarded($profile, $actionKey, $subject)) {
            return null;
        }

        // Daily cap: skip once the profile has hit the max awards for the day.
        if ($action->daily_cap !== null
            && $this->countToday($profile, $actionKey) >= $action->daily_cap) {
            return null;
        }

        $entry = DB::transaction(function () use ($profile, $action, $actionKey, $subject) {
            $entry = new XpLedgerEntry([
                'profile_id' => $profile->id,
                'action_key' => $actionKey,
                'points' => $action->points,
            ]);

            if ($subject !== null) {
                $entry->subject()->associate($subject);
            }

            $entry->save();

            // Atomic running total; points may be negative.
            Profile::query()->whereKey($profile->id)
                ->update(['xp_total' => DB::raw('xp_total + '.(int) $action->points)]);

            return $entry;
        });

        $profile->refresh();

        $this->syncRank($profile);

        XpAwarded::dispatch(
            $profile,
            (int) $action->points,
            $actionKey,
            (int) $profile->xp_total,
            $action->label,
        );

        return $entry;
    }

    /**
     * Recompute the profile's rank from its current xp_total and, when it has
     * advanced, persist the change, swap the rank badge and notify.
     */
    private function syncRank(Profile $profile): void
    {
        $newRank = Rank::query()
            ->where('min_xp', '<=', $profile->xp_total)
            ->orderByDesc('min_xp')
            ->orderByDesc('position')
            ->first();

        if ($newRank === null || $newRank->id === $profile->rank_id) {
            return;
        }

        $oldRank = $profile->rank_id !== null
            ? Rank::query()->find($profile->rank_id)
            : null;

        $profile->rank_id = $newRank->id;
        $profile->save();

        $this->swapRankBadge($profile, $newRank);

        // Only celebrate genuine promotions, never a downgrade caused by a
        // negative-points action.
        $isPromotion = $oldRank === null || $newRank->min_xp > $oldRank->min_xp;

        if ($isPromotion) {
            $profile->user->notify(new RankUnlocked($profile, $newRank));
        }
    }

    /**
     * Attach the new rank's badge and detach any other rank badges, leaving all
     * non-rank badge kinds untouched.
     */
    private function swapRankBadge(Profile $profile, Rank $rank): void
    {
        if ($rank->badge_id === null) {
            return;
        }

        $otherRankBadgeIds = Rank::query()
            ->whereNotNull('badge_id')
            ->where('badge_id', '!=', $rank->badge_id)
            ->pluck('badge_id');

        if ($otherRankBadgeIds->isNotEmpty()) {
            $profile->badges()->detach($otherRankBadgeIds->all());
        }

        $profile->badges()->syncWithoutDetaching([
            $rank->badge_id => ['awarded_at' => now()],
        ]);
    }

    private function subjectAlreadyAwarded(Profile $profile, string $actionKey, Model $subject): bool
    {
        return XpLedgerEntry::query()
            ->where('profile_id', $profile->id)
            ->where('action_key', $actionKey)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->exists();
    }

    private function countToday(Profile $profile, string $actionKey): int
    {
        return XpLedgerEntry::query()
            ->where('profile_id', $profile->id)
            ->where('action_key', $actionKey)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }
}
