<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'profile_id',
        'post_id',
        'status',
        'budget_cents',
        'spent_cents',
        'cpi_cents',
        'starts_at',
        'ends_at',
        'impressions_count',
        'clicks_count',
    ];

    /**
     * Mirror the DB defaults so freshly created (not yet re-fetched) models
     * expose zeroed counters instead of null.
     */
    protected $attributes = [
        'spent_cents' => 0,
        'impressions_count' => 0,
        'clicks_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'budget_cents' => 'integer',
            'spent_cents' => 'integer',
            'cpi_cents' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            $campaign->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function remainingCents(): int
    {
        // Counter attributes may be absent on a freshly created model (DB
        // defaults not yet hydrated), hence the int casts throughout.
        return max(0, (int) $this->budget_cents - (int) $this->spent_cents);
    }

    public function ctrPct(): float
    {
        if ((int) $this->impressions_count === 0) {
            return 0.0;
        }

        return round((int) $this->clicks_count / (int) $this->impressions_count * 100, 2);
    }

    /**
     * A non-completed campaign that is within its window and still has budget.
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereColumn('spent_cents', '<', 'budget_cents')
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
