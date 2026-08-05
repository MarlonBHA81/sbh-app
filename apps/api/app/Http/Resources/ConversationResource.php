<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Profile;
use App\Support\MessagePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        $active = $this->participants
            ->filter(fn (ConversationParticipant $p) => $p->left_at === null)
            ->values();

        $mine = $viewer
            ? $active->firstWhere(fn (ConversationParticipant $p) => $p->profile_id === $viewer->id)
            : null;

        return [
            'ulid' => $this->ulid,
            'kind' => $this->kind,
            'title' => $this->isDm() ? null : $this->title,
            'avatar_url' => $this->avatarUrl(),
            'rules' => $this->isGroup() ? $this->rules : null,
            'approval_status' => $this->isGroup() ? $this->approval_status : null,
            'participants' => $active->map(fn (ConversationParticipant $p) => [
                'role' => $p->role,
                'profile' => MessagePayload::liteProfile($p->profile),
            ])->all(),
            'last_message' => $this->relationLoaded('lastMessage')
                ? MessagePayload::liteMessage($this->lastMessage)
                : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'my_role' => $mine?->role,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
