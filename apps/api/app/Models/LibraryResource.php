<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A curated learning resource surfaced to members (V2 · LEARN): templates,
 * checklists, toolkits and AI prompts. Named LibraryResource to avoid colliding
 * with Filament's Resource base class; the DB table stays `resources`.
 */
class LibraryResource extends Model
{
    use SoftDeletes;

    protected $table = 'resources';

    /** Resource kinds shown as badges. */
    public const TYPES = [
        'template',
        'checklist',
        'toolkit',
        'ai_prompt',
    ];

    /** Categories used as member-facing filter chips. */
    public const CATEGORIES = [
        'marketing',
        'finance',
        'operations',
        'sales',
        'legal',
        'people',
    ];

    protected $fillable = [
        'ulid',
        'type',
        'category',
        'title',
        'description',
        'url',
        'industry',
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
        static::creating(function (LibraryResource $resource) {
            $resource->ulid ??= (string) Str::ulid();
        });

        static::saving(function (LibraryResource $resource) {
            // Stamp published_at the first time a resource goes live.
            if ($resource->is_published && $resource->published_at === null) {
                $resource->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'resource_saves', 'resource_id', 'profile_id')
            ->withTimestamps();
    }

    /** Published resources — what members should see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
