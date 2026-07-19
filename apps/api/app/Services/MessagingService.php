<?php

namespace App\Services;

use App\Events\ConversationBumped;
use App\Events\MessageDeleted;
use App\Events\MessageReacted;
use App\Events\MessageSent;
use App\Events\ReadReceipt;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Media;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for direct messages, group conversations, messages,
 * reactions, read receipts and participant management.
 */
class MessagingService
{
    public function __construct(private SafetyService $safety) {}

    /**
     * Find an existing DM between the two profiles (rejoining it if the
     * creator had left) or create a fresh one. Enforces block + DM-privacy.
     */
    public function findOrCreateDm(Profile $creator, Profile $target): Conversation
    {
        if ($creator->id === $target->id) {
            throw ValidationException::withMessages([
                'profile_ulid' => ['You cannot start a conversation with yourself.'],
            ]);
        }

        $this->assertNotBlocked($creator, $target);
        $this->assertDmAllowed($creator, $target);

        $conversation = Conversation::query()
            ->where('kind', Conversation::KIND_DM)
            ->whereHas('participants', fn ($q) => $q->where('profile_id', $creator->id))
            ->whereHas('participants', fn ($q) => $q->where('profile_id', $target->id))
            ->first();

        if ($conversation) {
            // Rejoin if the creator had previously left this DM.
            $mine = $conversation->participants()
                ->where('profile_id', $creator->id)
                ->first();

            if ($mine && $mine->left_at !== null) {
                $mine->update(['left_at' => null, 'joined_at' => now()]);
            }

            return $conversation->load('participants.profile');
        }

        return DB::transaction(function () use ($creator, $target) {
            $conversation = Conversation::create([
                'kind' => Conversation::KIND_DM,
                'created_by_profile_id' => $creator->id,
            ]);

            foreach ([$creator, $target] as $profile) {
                $conversation->participants()->create([
                    'profile_id' => $profile->id,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'joined_at' => now(),
                ]);
            }

            return $conversation->load('participants.profile');
        });
    }

    /**
     * Create a group conversation. The creator becomes owner; members that
     * block (or are blocked by) the creator are silently excluded.
     *
     * @param  list<string>  $memberUlids
     */
    public function createGroup(Profile $creator, string $title, array $memberUlids, ?string $rules = null): Conversation
    {
        $members = Profile::query()
            ->whereIn('ulid', $memberUlids)
            ->get()
            ->reject(fn (Profile $m) => $m->id === $creator->id
                || $this->safety->isBlockedBetween($creator, $m))
            ->unique('id')
            ->values();

        // Facilitators are trusted to run their own Spaces (Roles P2): their
        // groups launch immediately; everyone else's wait for admin approval.
        $facilitator = (bool) $creator->is_facilitator;

        return DB::transaction(function () use ($creator, $title, $rules, $members, $facilitator) {
            $conversation = Conversation::create([
                'kind' => Conversation::KIND_GROUP,
                'approval_status' => $facilitator
                    ? Conversation::APPROVAL_APPROVED
                    : Conversation::APPROVAL_PENDING,
                'approved_at' => $facilitator ? now() : null,
                'title' => $title,
                'rules' => $rules,
                'created_by_profile_id' => $creator->id,
            ]);

            $conversation->participants()->create([
                'profile_id' => $creator->id,
                'role' => ConversationParticipant::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            foreach ($members as $member) {
                $conversation->participants()->create([
                    'profile_id' => $member->id,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'joined_at' => now(),
                ]);
            }

            return $conversation->load('participants.profile');
        });
    }

    /**
     * Post a message to a conversation on behalf of the sender.
     *
     * @param  array{body?: string|null, media_ids?: list<string>, reply_to_ulid?: string|null}  $data
     */
    public function sendMessage(Conversation $conversation, Profile $sender, array $data): Message
    {
        if (! $conversation->isApproved()) {
            throw ValidationException::withMessages([
                'conversation' => [$conversation->approval_status === Conversation::APPROVAL_REJECTED
                    ? 'This group was not approved by the moderators.'
                    : 'This group is awaiting admin approval.'],
            ]);
        }

        $body = isset($data['body']) && trim((string) $data['body']) !== '' ? $data['body'] : null;
        $mediaIds = $data['media_ids'] ?? [];

        if ($body === null && $mediaIds === []) {
            throw ValidationException::withMessages([
                'body' => ['A message must contain text or at least one image.'],
            ]);
        }

        $media = $this->resolveMedia($sender, $mediaIds);
        $replyTo = $this->resolveReplyTo($conversation, $data['reply_to_ulid'] ?? null);

        $message = DB::transaction(function () use ($conversation, $sender, $body, $media, $replyTo) {
            $message = $conversation->messages()->create([
                'profile_id' => $sender->id,
                'body' => $body,
                'reply_to_message_id' => $replyTo?->id,
            ]);

            $media->each(function (Media $item, int $index) use ($message) {
                $item->mediable()->associate($message);
                $item->order = $index;
                $item->save();
            });

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'messages_count' => $conversation->messages_count + 1,
            ])->save();

            return $message;
        });

        $message->load(['profile', 'media', 'reactions', 'replyTo.profile', 'conversation']);

        broadcast(new MessageSent($message));
        $this->broadcastBump($conversation, $message, $sender);

        return $message;
    }

    public function deleteMessage(Message $message): void
    {
        $message->delete();

        broadcast(new MessageDeleted($message->loadMissing('conversation')));
    }

    public function addReaction(Message $message, Profile $profile, string $emoji): void
    {
        MessageReaction::query()->firstOrCreate([
            'message_id' => $message->id,
            'profile_id' => $profile->id,
            'emoji' => $emoji,
        ]);

        $this->broadcastReaction($message);
    }

    public function removeReaction(Message $message, Profile $profile, string $emoji): void
    {
        MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('profile_id', $profile->id)
            ->where('emoji', $emoji)
            ->delete();

        $this->broadcastReaction($message);
    }

    /**
     * Advance a participant's read cursor. Only moves forward.
     */
    public function markRead(Conversation $conversation, ConversationParticipant $participant, Message $message): void
    {
        if ($message->conversation_id !== $conversation->id) {
            throw ValidationException::withMessages([
                'message_ulid' => ['The message does not belong to this conversation.'],
            ]);
        }

        if (($participant->last_read_message_id ?? 0) >= $message->id) {
            return;
        }

        $participant->update(['last_read_message_id' => $message->id]);

        broadcast(new ReadReceipt($conversation, $participant->profile, $message));
    }

    /**
     * Add participants to a group (owner/admin). Blocked or already-active
     * profiles are skipped; profiles that had left are rejoined.
     *
     * @param  list<string>  $profileUlids
     */
    public function addParticipants(Conversation $conversation, Profile $actor, array $profileUlids): void
    {
        $profiles = Profile::query()->whereIn('ulid', $profileUlids)->get();

        foreach ($profiles as $profile) {
            if ($profile->id === $actor->id || $this->safety->isBlockedBetween($actor, $profile)) {
                continue;
            }

            $existing = $conversation->participants()
                ->where('profile_id', $profile->id)
                ->first();

            if ($existing === null) {
                $conversation->participants()->create([
                    'profile_id' => $profile->id,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'joined_at' => now(),
                ]);
            } elseif ($existing->left_at !== null) {
                $existing->update([
                    'left_at' => null,
                    'joined_at' => now(),
                    'role' => ConversationParticipant::ROLE_MEMBER,
                ]);
            }
        }
    }

    /**
     * A participant leaves the conversation. When the owner leaves a group the
     * role transfers to the oldest admin, else the oldest remaining member.
     */
    public function leave(Conversation $conversation, ConversationParticipant $participant): void
    {
        DB::transaction(function () use ($conversation, $participant) {
            if ($participant->isOwner() && $conversation->isGroup()) {
                $this->transferOwnership($conversation, $participant);
            }

            $participant->update(['left_at' => now()]);
        });
    }

    /**
     * Remove another participant (owner/admin action).
     */
    public function removeParticipant(ConversationParticipant $target): void
    {
        $target->update(['left_at' => now()]);
    }

    public function transferOwnership(Conversation $conversation, ConversationParticipant $leaving): void
    {
        $successor = $conversation->participants()
            ->active()
            ->where('id', '!=', $leaving->id)
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")
            ->orderBy('joined_at')
            ->orderBy('id')
            ->first();

        $successor?->update(['role' => ConversationParticipant::ROLE_OWNER]);
    }

    /**
     * Resolve message media ulids: images only, owned by the sender, not yet
     * attached, at most four, order preserved.
     *
     * @param  list<string>  $ulids
     * @return Collection<int, Media>
     */
    private function resolveMedia(Profile $sender, array $ulids): Collection
    {
        if ($ulids === []) {
            return new Collection;
        }

        if (count($ulids) > 4) {
            throw ValidationException::withMessages([
                'media_ids' => ['A message may attach at most four images.'],
            ]);
        }

        $media = Media::query()->whereIn('ulid', $ulids)->get()->keyBy('ulid');

        foreach ($ulids as $ulid) {
            $item = $media->get($ulid);

            if (! $item || $item->profile_id !== $sender->id) {
                throw ValidationException::withMessages([
                    'media_ids' => ['One or more images do not exist or do not belong to you.'],
                ]);
            }

            if ($item->type !== Media::TYPE_IMAGE) {
                throw ValidationException::withMessages([
                    'media_ids' => ['Only images may be attached to a message.'],
                ]);
            }

            if ($item->isAttached()) {
                throw ValidationException::withMessages([
                    'media_ids' => ['One or more images are already attached elsewhere.'],
                ]);
            }
        }

        return (new Collection($ulids))->map(fn (string $ulid) => $media->get($ulid))->values();
    }

    private function resolveReplyTo(Conversation $conversation, ?string $ulid): ?Message
    {
        if ($ulid === null) {
            return null;
        }

        $message = Message::query()->where('ulid', $ulid)->first();

        if (! $message || $message->conversation_id !== $conversation->id) {
            throw ValidationException::withMessages([
                'reply_to_ulid' => ['You can only reply to a message in the same conversation.'],
            ]);
        }

        return $message;
    }

    private function assertNotBlocked(Profile $creator, Profile $target): void
    {
        if (! $this->safety->isBlockedBetween($creator, $target)) {
            return;
        }

        // The creator blocked the target: surface a 403. The target blocked the
        // creator: the target is invisible, so a 404 keeps the block private.
        abort($this->safety->viewerBlocked($creator, $target) ? 403 : 404);
    }

    private function assertDmAllowed(Profile $creator, Profile $target): void
    {
        $privacy = $target->dm_privacy ?? Profile::DM_PRIVACY_EVERYONE;

        if ($privacy === Profile::DM_PRIVACY_NO_ONE) {
            abort(403, __('This profile is not accepting direct messages.'));
        }

        // "followers" = the target only accepts DMs from profiles they follow.
        if ($privacy === Profile::DM_PRIVACY_FOLLOWERS && ! $creator->isFollowedBy($target)) {
            abort(403, __('This profile only accepts direct messages from profiles they follow.'));
        }
    }

    private function broadcastReaction(Message $message): void
    {
        broadcast(new MessageReacted(
            $message->load(['reactions', 'conversation'])
        ));
    }

    private function broadcastBump(Conversation $conversation, Message $message, Profile $sender): void
    {
        $recipients = $conversation->participants()
            ->active()
            ->where('profile_id', '!=', $sender->id)
            ->with('profile:id,ulid')
            ->get()
            ->pluck('profile.ulid')
            ->filter()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        broadcast(new ConversationBumped($conversation, $message, $recipients));
    }
}
