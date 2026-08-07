<?php

namespace App\Models;

use App\Support\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A development deliverable tracked against a supplier enrolment, with a simple
 * pending → complete lifecycle. Transitions are recorded in the activity log.
 */
class ProgrammeMilestone extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETE = 'complete';

    protected $fillable = [
        'supplier_enrolment_id',
        'title',
        'due_at',
        'status',
        'completed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProgrammeMilestone $milestone) {
            $milestone->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Restrict to milestones under programmes owned by a corporate. */
    public function scopeForCorporate(Builder $query, Profile $corporate): Builder
    {
        return $query->whereHas(
            'enrolment.cohort.programme',
            fn (Builder $programme) => $programme->where('profile_id', $corporate->id)
        );
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(SupplierEnrolment::class, 'supplier_enrolment_id');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    // --- State machine --------------------------------------------------------

    /** Mark the milestone complete. */
    public function markComplete(?User $actor = null): void
    {
        $this->update(['status' => self::STATUS_COMPLETE, 'completed_at' => now()]);

        Activity::log('milestone.complete', $this, ['enrolment_id' => $this->supplier_enrolment_id], $actor);
    }

    /** Reopen a completed milestone. */
    public function reopen(?User $actor = null): void
    {
        $this->update(['status' => self::STATUS_PENDING, 'completed_at' => null]);

        Activity::log('milestone.reopen', $this, ['enrolment_id' => $this->supplier_enrolment_id], $actor);
    }
}
