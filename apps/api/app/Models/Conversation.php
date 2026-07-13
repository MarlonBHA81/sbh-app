<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    public const KIND_DM = 'dm';

    public const KIND_GROUP = 'group';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'ulid',
        'kind',
        'approval_status',
        'approved_at',
        'title',
        'avatar_path',
        'rules',
        'created_by_profile_id',
        'last_message_at',
        'messages_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'approved_at' => 'datetime',
            'messages_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'created_by_profile_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The most recent message, kept even when soft-deleted so the list can
     * still render a tombstone preview.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->withTrashed()->latestOfMany();
    }

    /**
     * Absolute URL to the group avatar, or null when none is set.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk('public')->url($this->avatar_path);
    }

    public function isDm(): bool
    {
        return $this->kind === self::KIND_DM;
    }

    public function isGroup(): bool
    {
        return $this->kind === self::KIND_GROUP;
    }

    /** DMs are always usable; groups only once an admin approves them. */
    public function isApproved(): bool
    {
        return $this->isDm() || $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * The active (not left) participant row for the given profile, if any.
     */
    public function participantFor(?Profile $profile): ?ConversationParticipant
    {
        if ($profile === null) {
            return null;
        }

        return $this->participants
            ->firstWhere(fn (ConversationParticipant $p) => $p->profile_id === $profile->id && $p->left_at === null);
    }

    /**
     * Whether the given profile is an active participant of this conversation.
     */
    public function hasActiveParticipant(?Profile $profile): bool
    {
        return $this->participantFor($profile) !== null;
    }
}
