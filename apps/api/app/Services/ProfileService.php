<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\FollowAccepted;
use App\Notifications\FollowRequested;
use App\Notifications\NewFollower;
use App\Support\Handles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public const MAX_BUSINESS_PROFILES = 3;

    public function createPersonalProfile(User $user, ?string $handle = null): Profile
    {
        return $user->profiles()->create([
            'kind' => Profile::KIND_PERSONAL,
            'name' => $user->name,
            'handle' => Handles::generate($handle, $user->name, $user->email),
        ]);
    }

    public function createBusinessProfile(User $user, array $data): Profile
    {
        $max = (int) Setting::get('max_business_profiles', self::MAX_BUSINESS_PROFILES);

        if ($user->businessProfiles()->count() >= $max) {
            throw ValidationException::withMessages([
                'kind' => ['You may create at most '.$max.' business profiles.'],
            ]);
        }

        return $user->profiles()->create([
            'kind' => Profile::KIND_BUSINESS,
            'name' => $data['name'],
            'handle' => Handles::generate($data['handle'] ?? null, $data['name'], $user->email),
            'bio' => $data['bio'] ?? null,
            'category' => $data['category'] ?? null,
            'website' => $data['website'] ?? null,
            'location' => $data['location'] ?? null,
            'is_private' => $data['is_private'] ?? false,
        ]);
    }

    public function updateProfile(Profile $profile, array $data): Profile
    {
        $profile->fill($data)->save();

        return $profile->refresh();
    }

    public function deleteBusinessProfile(Profile $profile): void
    {
        if (! $profile->isBusiness()) {
            throw ValidationException::withMessages([
                'profile' => ['Only business profiles can be deleted.'],
            ]);
        }

        $profile->delete();
    }

    /**
     * Follow a target profile. Returns the follow (existing or new).
     */
    public function follow(Profile $actor, Profile $target): Follow
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'profile' => ['You cannot follow yourself.'],
            ]);
        }

        $existing = Follow::query()
            ->where('follower_profile_id', $actor->id)
            ->where('followed_profile_id', $target->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $state = $target->is_private ? Follow::STATE_PENDING : Follow::STATE_ACCEPTED;

        $follow = DB::transaction(function () use ($actor, $target, $state) {
            $follow = Follow::create([
                'follower_profile_id' => $actor->id,
                'followed_profile_id' => $target->id,
                'state' => $state,
            ]);

            if ($state === Follow::STATE_ACCEPTED) {
                $this->incrementFollowCounters($actor, $target);
            }

            return $follow;
        });

        if ($actor->user_id !== $target->user_id) {
            $notification = $state === Follow::STATE_ACCEPTED
                ? new NewFollower(actor: $actor, recipient: $target)
                : new FollowRequested(actor: $actor, recipient: $target);

            $target->user->notify($notification);
        }

        return $follow;
    }

    /**
     * Unfollow (or cancel a pending request).
     */
    public function unfollow(Profile $actor, Profile $target): void
    {
        $follow = Follow::query()
            ->where('follower_profile_id', $actor->id)
            ->where('followed_profile_id', $target->id)
            ->first();

        if (! $follow) {
            return;
        }

        DB::transaction(function () use ($follow, $actor, $target) {
            $wasAccepted = $follow->state === Follow::STATE_ACCEPTED;

            $follow->delete();

            if ($wasAccepted) {
                $this->decrementFollowCounters($actor, $target);
            }
        });
    }

    public function acceptFollowRequest(Follow $follow): Follow
    {
        if (! $follow->isPending()) {
            return $follow;
        }

        $follow = DB::transaction(function () use ($follow) {
            $follow->update(['state' => Follow::STATE_ACCEPTED]);

            $this->incrementFollowCounters($follow->follower, $follow->followed);

            return $follow;
        });

        $follower = $follow->follower;
        $followed = $follow->followed;

        if ($follower->user_id !== $followed->user_id) {
            $follower->user->notify(new FollowAccepted(actor: $followed, recipient: $follower));
        }

        return $follow;
    }

    public function declineFollowRequest(Follow $follow): void
    {
        if ($follow->isPending()) {
            $follow->delete();
        }
    }

    private function incrementFollowCounters(Profile $follower, Profile $followed): void
    {
        $follower->increment('following_count');
        $followed->increment('followers_count');
    }

    private function decrementFollowCounters(Profile $follower, Profile $followed): void
    {
        if ($follower->following_count > 0) {
            $follower->decrement('following_count');
        }

        if ($followed->followers_count > 0) {
            $followed->decrement('followers_count');
        }
    }
}
