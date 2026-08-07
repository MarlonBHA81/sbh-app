<?php

namespace App\Models;

use App\Support\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A unit of ED/SD spend against a supplier enrolment. A row is "planned" while
 * disbursed_at is null and "actual" once it is set, so a programme can report
 * committed-vs-paid.
 */
class Disbursement extends Model
{
    use HasFactory;

    public const KIND_GRANT = 'grant';

    public const KIND_LOAN = 'loan';

    public const KIND_IN_KIND = 'in_kind';

    protected $fillable = [
        'supplier_enrolment_id',
        'amount_cents',
        'currency',
        'kind',
        'disbursed_at',
        'reference',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'disbursed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Disbursement $disbursement) {
            $disbursement->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Restrict to disbursements under programmes owned by a corporate. */
    public function scopeForCorporate(Builder $query, Profile $corporate): Builder
    {
        return $query->whereHas(
            'enrolment.cohort.programme',
            fn (Builder $programme) => $programme->where('profile_id', $corporate->id)
        );
    }

    /** Only lines that have actually been paid out. */
    public function scopeActual(Builder $query): Builder
    {
        return $query->whereNotNull('disbursed_at');
    }

    /** Only committed-but-not-yet-paid lines. */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->whereNull('disbursed_at');
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(SupplierEnrolment::class, 'supplier_enrolment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->disbursed_at !== null;
    }

    /** Record the line as actually paid out now (if not already). */
    public function markDisbursed(?User $actor = null): void
    {
        $this->update(['disbursed_at' => $this->disbursed_at ?? now()]);

        Activity::log('disbursement.paid', $this, ['enrolment_id' => $this->supplier_enrolment_id], $actor);
    }
}
