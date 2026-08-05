<?php

namespace App\Support;

use App\Models\Message;
use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Support\Str;

/**
 * Presents a message as the canonical array shape shared by MessageResource
 * (REST) and the MessageSent broadcast event, so both stay in sync.
 */
class MessagePayload
{
    public const PREVIEW_LENGTH = 100;

    public const PHOTO_PLACEHOLDER = '📷 Photo';

    /**
     * Full message representation. When a block exists in either direction
     * between the viewer and the sender the body/media are masked and the
     * bubble is flagged hidden so the timeline slot is preserved.
     *
     * @return array<string, mixed>
     */
    public static function present(Message $message, ?Profile $viewer): array
    {
        $sender = $message->profile;

        $hidden = $viewer !== null
            && app(SafetyService::class)->isBlockedBetween($viewer, $sender);

        $deleted = $message->trashed();

        return [
            'ulid' => $message->ulid,
            'conversation_ulid' => $message->relationLoaded('conversation')
                ? $message->conversation->ulid
                : null,
            'body' => ($hidden || $deleted) ? null : $message->body,
            'hidden' => $hidden,
            'deleted' => $deleted,
            'sender' => self::liteProfile($sender),
            'reply_to' => $message->reply_to_message_id === null
                ? null
                : self::liteMessage($message->replyTo),
            'reactions' => self::reactions($message, $viewer),
            'media' => ($hidden || $deleted)
                ? []
                : $message->media->map(fn ($m) => [
                    'ulid' => $m->ulid,
                    'type' => $m->type,
                    'url' => $m->url(),
                    'thumb_url' => $m->thumbUrl(),
                    'width' => $m->width,
                    'height' => $m->height,
                ])->all(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * Lightweight preview used for a conversation's last_message and for the
     * reply_to reference on a message.
     *
     * @return array<string, mixed>|null
     */
    public static function liteMessage(?Message $message): ?array
    {
        if ($message === null) {
            return null;
        }

        return [
            'ulid' => $message->ulid,
            'preview' => self::preview($message),
            'sender_handle' => $message->profile?->handle,
            'created_at' => $message->created_at?->toISOString(),
            'deleted' => $message->trashed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function liteProfile(Profile $profile): array
    {
        return [
            'ulid' => $profile->ulid,
            'handle' => $profile->handle,
            'name' => $profile->name,
            'avatar_url' => $profile->avatarUrl(),
        ];
    }

    /**
     * Body preview truncated to PREVIEW_LENGTH, or a photo placeholder when
     * the message is image-only. Null for tombstoned messages.
     */
    public static function preview(Message $message): ?string
    {
        if ($message->trashed()) {
            return null;
        }

        if ($message->body !== null && trim($message->body) !== '') {
            return Str::limit($message->body, self::PREVIEW_LENGTH);
        }

        return self::PHOTO_PLACEHOLDER;
    }

    /**
     * Reactions grouped by emoji with a per-viewer "reacted_by_me" flag.
     *
     * @return list<array{emoji: string, count: int, reacted_by_me: bool}>
     */
    public static function reactions(Message $message, ?Profile $viewer): array
    {
        return $message->reactions
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $group->count(),
                'reacted_by_me' => $viewer !== null
                    && $group->contains(fn ($r) => $r->profile_id === $viewer->id),
            ])
            ->values()
            ->all();
    }
}
