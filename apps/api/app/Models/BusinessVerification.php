<?php

namespace App\Models;

use App\Support\Moderation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A business profile's verification submission and its review state machine:
 * pending → reviewing → approved | rejected. Approving flips the profile's
 * is_verified flag; every transition is recorded in moderation_actions.
 */
class BusinessVerification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Statuses that block a fresh submission for the same profile. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_REVIEWING, self::STATUS_APPROVED];

    public const TYPE_ID = 'id_document';

    public const TYPE_CIPC = 'cipc';

    public const TYPE_BBEE = 'bbee';

    protected $fillable = [
        'profile_id',
        'status',
        'legal_name',
        'registration_number',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BusinessVerification $verification) {
            $verification->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVIEWING], true);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessVerificationDocument::class);
    }

    // --- State machine --------------------------------------------------------

    /** Move a pending submission into review. */
    public function markReviewing(User $reviewer): void
    {
        $this->update(['status' => self::STATUS_REVIEWING, 'reviewed_by' => $reviewer->id]);

        Moderation::log('verification.review', $this, [], $reviewer);
    }

    /** Approve the submission and mark the business profile verified. */
    public function approve(User $reviewer, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_note' => $note,
        ]);

        $this->profile->forceFill(['is_verified' => true])->save();

        Moderation::log('verification.approve', $this, ['profile_id' => $this->profile_id], $reviewer);
    }

    /** Reject the submission with a reason (the business may resubmit). */
    public function reject(User $reviewer, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_note' => $reason,
        ]);

        Moderation::log('verification.reject', $this, ['reason' => $reason], $reviewer);
    }
}
