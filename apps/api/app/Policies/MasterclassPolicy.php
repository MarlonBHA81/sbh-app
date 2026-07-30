<?php

namespace App\Policies;

use App\Models\Masterclass;
use App\Models\Profile;
use App\Models\User;

/**
 * Who may create, manage and host masterclasses. Mirrors the challenge model:
 * facilitators (the flag lives on the acting profile) can author their own,
 * admins can manage any. created_by holds the authoring User id.
 */
class MasterclassPolicy
{
    /** Facilitators (or any admin) may create a masterclass. */
    public function create(User $user, Profile $actor): bool
    {
        return $user->isAdmin() || $actor->is_facilitator;
    }

    /** The facilitator who created it may manage it; admins may manage any. */
    public function update(User $user, Masterclass $masterclass, Profile $actor): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $actor->is_facilitator && $masterclass->created_by === $user->id;
    }

    public function delete(User $user, Masterclass $masterclass, Profile $actor): bool
    {
        return $this->update($user, $masterclass, $actor);
    }

    /** Hosting a live session — the creator or an admin. */
    public function host(User $user, Masterclass $masterclass): bool
    {
        return $user->isAdmin() || $masterclass->created_by === $user->id;
    }
}
