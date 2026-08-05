<?php

namespace App\Models;

use Database\Factories\BusinessNeedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BusinessNeed extends Model
{
    /** @use HasFactory<BusinessNeedFactory> */
    use HasFactory;

    public const KIND_OFFERING = 'offering';

    public const KIND_SEEKING = 'seeking';

    protected $fillable = [
        'profile_id',
        'kind',
        'business_category_id',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BusinessNeed $need) {
            $need->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The opposite kind that a reciprocal need must have to be a match.
     */
    public static function opposite(string $kind): string
    {
        return $kind === self::KIND_OFFERING ? self::KIND_SEEKING : self::KIND_OFFERING;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
