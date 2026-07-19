<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\Profile;
use App\Models\User;

/**
 * Who may create and manage challenges (Roles P2). Authorisation depends on the
 * ACTING profile (facilitator flag lives on the profile), so the active profile
 * is passed as an extra argument. Admins may always manage.
 */
class ChallengePolicy
{
    /** Facilitators (or any admin) may create a challenge. */
    public function create(User $user, Profile $actor): bool
    {
        return $user->isAdmin() || $actor->is_facilitator;
    }

    /** The facilitator who created it may manage it; admins may manage any. */
    public function update(User $user, Challenge $challenge, Profile $actor): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $actor->is_facilitator
            && $challenge->created_by_profile_id === $actor->id;
    }
}
