<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A cohort (intake) within a programme. Supplier enrolments (ESD-2) attach here.
 */
class Cohort extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'programme_id',
        'name',
        'starts_at',
        'ends_at',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Cohort $cohort) {
            $cohort->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
