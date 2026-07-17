<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An admin-curated wellness prompt or short read (V3 · BELONG). Supportive
 * content only — never gamified.
 */
class WellnessResource extends Model
{
    use SoftDeletes;

    /** Gentle groupings shown as quiet section labels. */
    public const CATEGORIES = [
        'reflection',
        'encouragement',
        'rest',
        'connection',
        'focus',
    ];

    protected $fillable = [
        'ulid',
        'category',
        'title',
        'body',
        'position',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WellnessResource $resource) {
            $resource->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
