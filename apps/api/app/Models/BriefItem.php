<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An admin-curated briefing snippet for the Daily Business Brief
 * (V2 · Feature 7). Optionally targeted at an `industry`; a null industry means
 * it applies to everyone. Only published items are shown.
 */
class BriefItem extends Model
{
    use SoftDeletes;

    /** Kinds shown as a small badge on the brief card. */
    public const KINDS = [
        'tip',
        'regulation',
        'news',
        'resource',
    ];

    protected $fillable = [
        'ulid',
        'kind',
        'title',
        'body',
        'industry',
        'url',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BriefItem $item) {
            $item->ulid ??= (string) Str::ulid();
        });

        static::saving(function (BriefItem $item) {
            // Stamp published_at the first time an item goes live.
            if ($item->is_published && $item->published_at === null) {
                $item->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Published items — what members should see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
