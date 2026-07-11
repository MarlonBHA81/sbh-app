<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Models\Rank;

/**
 * System notification fired when a profile reaches a new rank.
 *
 * Unlike engagement notifications there is no distinct actor: the recipient is
 * both the actor and the target, so the usual self-action suppression that call
 * sites apply does not apply here.
 */
class RankUnlocked extends ActivityNotification
{
    public function __construct(
        public Profile $profile,
        public Rank $rank,
    ) {
        parent::__construct(
            actor: $profile,
            recipient: $profile,
            preview: 'You reached '.$rank->name.'!',
        );
    }

    public function type(): string
    {
        return 'rank_unlocked';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'rank' => [
                'key' => $this->rank->key,
                'name' => $this->rank->name,
                'icon' => $this->rank->icon,
                'min_xp' => $this->rank->min_xp,
            ],
        ];
    }
}
