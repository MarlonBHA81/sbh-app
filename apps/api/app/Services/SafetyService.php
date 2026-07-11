<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Mute;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central authority for blocks and mutes.
 *
 * Registered as a singleton so the block/mute id sets are resolved once per
 * request and reused across FeedService, resources, controllers and models.
 */
class SafetyService
{
    /** @var array<int, list<int>> blocked-with (bidirectional) ids keyed by profile id */
    private array $blockedCache = [];

    /** @var array<int, list<int>> muted ids keyed by muter profile id */
    private array $mutedCache = [];

    /**
     * Profile ids the given profile can neither see nor be seen by. This is
     * bidirectional: it is the union of profiles this profile blocked and
     * profiles that blocked this profile.
     *
     * @return list<int>
     */
    public function blockedProfileIds(Profile $profile): array
    {
        return $this->blockedCache[$profile->id] ??= Block::query()
            ->where('blocker_profile_id', $profile->id)
            ->pluck('blocked_profile_id')
            ->merge(
                Block::query()
                    ->where('blocked_profile_id', $profile->id)
                    ->pluck('blocker_profile_id')
            )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Profile ids this profile has muted (one-directional).
     *
     * @return list<int>
     */
    public function mutedProfileIds(Profile $profile): array
    {
        return $this->mutedCache[$profile->id] ??= Mute::query()
            ->where('muter_profile_id', $profile->id)
            ->pluck('muted_profile_id')
            ->all();
    }

    /**
     * Author ids excluded from a viewer's feeds: blocked (either direction)
     * plus muted.
     *
     * @return list<int>
     */
    public function feedExcludedProfileIds(Profile $viewer): array
    {
        return array_values(array_unique(array_merge(
            $this->blockedProfileIds($viewer),
            $this->mutedProfileIds($viewer),
        )));
    }

    /**
     * Whether a block exists in either direction between the two profiles.
     */
    public function isBlockedBetween(?Profile $a, ?Profile $b): bool
    {
        if ($a === null || $b === null || $a->id === $b->id) {
            return false;
        }

        return in_array($b->id, $this->blockedProfileIds($a), true);
    }

    /**
     * Whether $viewer has blocked $target (viewer is the blocker).
     */
    public function viewerBlocked(?Profile $viewer, ?Profile $target): bool
    {
        if ($viewer === null || $target === null || $viewer->id === $target->id) {
            return false;
        }

        return Block::query()
            ->where('blocker_profile_id', $viewer->id)
            ->where('blocked_profile_id', $target->id)
            ->exists();
    }

    public function isMuted(Profile $muter, Profile $muted): bool
    {
        return in_array($muted->id, $this->mutedProfileIds($muter), true);
    }

    /**
     * Block $target on behalf of $actor. Tears down follows in both
     * directions (fixing counters) and removes pending requests.
     */
    public function block(Profile $actor, Profile $target): Block
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'profile' => ['You cannot block yourself.'],
            ]);
        }

        $block = DB::transaction(function () use ($actor, $target) {
            $block = Block::query()->firstOrCreate([
                'blocker_profile_id' => $actor->id,
                'blocked_profile_id' => $target->id,
            ]);

            $this->teardownFollow($actor, $target);
            $this->teardownFollow($target, $actor);

            return $block;
        });

        $this->flush();

        return $block;
    }

    public function unblock(Profile $actor, Profile $target): void
    {
        Block::query()
            ->where('blocker_profile_id', $actor->id)
            ->where('blocked_profile_id', $target->id)
            ->delete();

        $this->flush();
    }

    public function mute(Profile $actor, Profile $target): Mute
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'profile' => ['You cannot mute yourself.'],
            ]);
        }

        $mute = Mute::query()->firstOrCreate([
            'muter_profile_id' => $actor->id,
            'muted_profile_id' => $target->id,
        ]);

        $this->flush();

        return $mute;
    }

    public function unmute(Profile $actor, Profile $target): void
    {
        Mute::query()
            ->where('muter_profile_id', $actor->id)
            ->where('muted_profile_id', $target->id)
            ->delete();

        $this->flush();
    }

    /**
     * Remove any follow (accepted or pending) from $follower to $followed,
     * adjusting counters when an accepted follow is removed.
     */
    private function teardownFollow(Profile $follower, Profile $followed): void
    {
        $follow = Follow::query()
            ->where('follower_profile_id', $follower->id)
            ->where('followed_profile_id', $followed->id)
            ->first();

        if (! $follow) {
            return;
        }

        $wasAccepted = $follow->state === Follow::STATE_ACCEPTED;

        $follow->delete();

        if ($wasAccepted) {
            if ($follower->following_count > 0) {
                $follower->decrement('following_count');
            }
            if ($followed->followers_count > 0) {
                $followed->decrement('followers_count');
            }
        }
    }

    /**
     * Drop the per-request caches after a mutation so subsequent reads within
     * the same request reflect the change.
     */
    private function flush(): void
    {
        $this->blockedCache = [];
        $this->mutedCache = [];
    }
}
