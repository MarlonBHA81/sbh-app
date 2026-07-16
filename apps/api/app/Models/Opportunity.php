<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A growth opportunity surfaced to members (V1 · GROW pillar).
 */
class Opportunity extends Model
{
    use SoftDeletes;

    /** Opportunity kinds shown as filter chips and badges. */
    public const TYPES = [
        'tender',
        'funding',
        'grant',
        'procurement',
        'programme',
        'competition',
    ];

    protected $fillable = [
        'ulid',
        'type',
        'title',
        'description',
        'organisation',
        'url',
        'industry',
        'province',
        'amount',
        'closes_at',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'closes_at' => 'date',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity) {
            $opportunity->ulid ??= (string) Str::ulid();
        });

        static::saving(function (Opportunity $opportunity) {
            // Stamp published_at the first time an opportunity goes live.
            if ($opportunity->is_published && $opportunity->published_at === null) {
                $opportunity->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'opportunity_saves')
            ->withTimestamps();
    }

    /**
     * Published and not past its closing date — what members should see.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('closes_at')->orWhereDate('closes_at', '>=', now()->toDateString());
            });
    }

    public function isOpen(): bool
    {
        return $this->closes_at === null || ! $this->closes_at->isPast();
    }
}
