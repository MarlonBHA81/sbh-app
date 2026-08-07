<?php

namespace App\Models;

use App\Support\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A supplier business's participation in a cohort, with its review state
 * machine: invited/applied → accepted → active → completed (or withdrawn /
 * rejected). Every transition is recorded in the general activity log.
 */
class SupplierEnrolment extends Model
{
    use HasFactory;

    /** Corporate invited the supplier; awaiting the supplier's acceptance. */
    public const STATUS_INVITED = 'invited';

    /** Supplier applied to an open programme; awaiting the corporate's decision. */
    public const STATUS_APPLIED = 'applied';

    /** The enrolment is confirmed but the supplier is not yet actively developing. */
    public const STATUS_ACCEPTED = 'accepted';

    /** The supplier is actively in the development programme. */
    public const STATUS_ACTIVE = 'active';

    /** The supplier finished the programme. */
    public const STATUS_COMPLETED = 'completed';

    /** The supplier pulled out. */
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** The corporate declined the invite/application. */
    public const STATUS_REJECTED = 'rejected';

    /** Statuses that count as an occupied seat / block a fresh enrolment. */
    public const OPEN_STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_APPLIED,
        self::STATUS_ACCEPTED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'cohort_id',
        'profile_id',
        'status',
        'enrolled_at',
        'decision_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierEnrolment $enrolment) {
            $enrolment->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Restrict to enrolments whose cohort's programme is owned by a corporate. */
    public function scopeForCorporate(Builder $query, Profile $corporate): Builder
    {
        return $query->whereHas('cohort.programme', fn (Builder $programme) => $programme->where('profile_id', $corporate->id));
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** The supplier business profile. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProgrammeMilestone::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_INVITED, self::STATUS_APPLIED], true);
    }

    // --- Development rollups (foundation for ESD-4 reporting) ------------------

    /**
     * Milestone completion for this enrolment.
     *
     * @return array{total: int, complete: int}
     */
    public function milestoneProgress(): array
    {
        $total = $this->milestones()->count();
        $complete = $this->milestones()->where('status', ProgrammeMilestone::STATUS_COMPLETE)->count();

        return ['total' => $total, 'complete' => $complete];
    }

    /** Total spend actually paid out (disbursed_at set). */
    public function actualDisbursedCents(): int
    {
        return (int) $this->disbursements()->whereNotNull('disbursed_at')->sum('amount_cents');
    }

    /** Total spend committed but not yet paid (disbursed_at null). */
    public function plannedDisbursedCents(): int
    {
        return (int) $this->disbursements()->whereNull('disbursed_at')->sum('amount_cents');
    }

    // --- State machine --------------------------------------------------------

    /** Confirm the enrolment (supplier accepts an invite, or corporate accepts an application). */
    public function accept(?User $actor = null, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'enrolled_at' => $this->enrolled_at ?? now(),
            'decision_note' => $note,
        ]);

        Activity::log('enrolment.accept', $this, ['cohort_id' => $this->cohort_id], $actor);
    }

    /** Start active development for the supplier. */
    public function activate(?User $actor = null): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'enrolled_at' => $this->enrolled_at ?? now(),
        ]);

        Activity::log('enrolment.activate', $this, ['cohort_id' => $this->cohort_id], $actor);
    }

    /** Mark the supplier as having completed the programme. */
    public function complete(?User $actor = null): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);

        Activity::log('enrolment.complete', $this, ['cohort_id' => $this->cohort_id], $actor);
    }

    /** Decline the invite/application with a reason. */
    public function reject(?User $actor = null, ?string $reason = null): void
    {
        $this->update(['status' => self::STATUS_REJECTED, 'decision_note' => $reason]);

        Activity::log('enrolment.reject', $this, ['cohort_id' => $this->cohort_id, 'reason' => $reason], $actor);
    }

    /** The supplier pulls out of the enrolment. */
    public function withdraw(?User $actor = null): void
    {
        $this->update(['status' => self::STATUS_WITHDRAWN]);

        Activity::log('enrolment.withdraw', $this, ['cohort_id' => $this->cohort_id], $actor);
    }
}
