<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An ESD programme run by a corporate sponsor. Owns cohorts of suppliers.
 */
class Programme extends Model
{
    use HasFactory;

    public const TYPE_ENTERPRISE_DEVELOPMENT = 'enterprise_development';

    public const TYPE_SUPPLIER_DEVELOPMENT = 'supplier_development';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'profile_id',
        'name',
        'description',
        'type',
        'status',
        'starts_at',
        'ends_at',
        'budget_cents',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'budget_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Programme $programme) {
            $programme->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Restrict to programmes owned by a given corporate profile. */
    public function scopeForCorporate(Builder $query, Profile $corporate): Builder
    {
        return $query->where('profile_id', $corporate->id);
    }

    /** The corporate profile that runs this programme. */
    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }
}
