<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Profile;

/**
 * Tells a group creator their group was approved (usable) or rejected.
 * Actor == recipient: this is a system decision addressed to the creator.
 */
class GroupApprovalDecided extends ActivityNotification
{
    public function __construct(
        Profile $creator,
        public Conversation $conversation,
        public bool $approved,
    ) {
        parent::__construct(
            actor: $creator,
            recipient: $creator,
            preview: $approved
                ? '"'.($conversation->title ?? 'Your group').'" was approved — start chatting!'
                : '"'.($conversation->title ?? 'Your group').'" was not approved.',
        );
    }

    public function type(): string
    {
        return $this->approved ? 'group_approved' : 'group_rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'conversation_ulid' => $this->conversation->ulid,
        ];
    }
}
