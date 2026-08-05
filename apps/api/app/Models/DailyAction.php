<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A small daily action (V1 · PROGRESS) — "ask a customer for a testimonial",
 * "update your Google Business Profile". One is surfaced per day, rotating
 * deterministically so everyone sees the same action on a given date.
 */
class DailyAction extends Model
{
    protected $fillable = [
        'ulid',
        'title',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (DailyAction $action) {
            $action->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The active action for a given date, chosen deterministically by rotating
     * the active pool by day number. Returns null when no actions are set up.
     */
    public static function forDate(?Carbon $date = null): ?self
    {
        $actions = static::query()->active()->orderBy('id')->get();

        if ($actions->isEmpty()) {
            return null;
        }

        $dayNumber = (int) floor(($date ?? now())->startOfDay()->timestamp / 86400);

        return $actions[$dayNumber % $actions->count()];
    }
}
