<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * A growth opportunity surfaced to members (V1 · GROW pillar).
 */
class Opportunity extends Model
{
    use Searchable, SoftDeletes;

    /**
     * Text fields an opportunity search matches against.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'organisation' => $this->organisation,
        ];
    }

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
        'source',
        'source_url',
        'source_ref',
        'is_official',
        'is_sponsored',
        'sponsor_name',
        'sponsor_url',
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
            'is_official' => 'boolean',
            'is_sponsored' => 'boolean',
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

    /** Metrics-first ad events (impressions/clicks) for a sponsored opportunity. */
    public function adEvents(): HasMany
    {
        return $this->hasMany(AdEvent::class);
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
