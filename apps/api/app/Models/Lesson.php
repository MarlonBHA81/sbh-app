<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A bite-size (~5-minute) learning module (V2 · LEARN). Members read the body
 * (or follow an external link) and mark it complete, which awards XP once.
 */
class Lesson extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'lesson_track_id',
        'title',
        'body',
        'external_url',
        'minutes',
        'journey_stage',
        'position',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson) {
            $lesson->ulid ??= (string) Str::ulid();
        });

        static::saving(function (Lesson $lesson) {
            if ($lesson->is_published && $lesson->published_at === null) {
                $lesson->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(LessonTrack::class, 'lesson_track_id');
    }

    public function completedBy(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'lesson_completions', 'lesson_id', 'profile_id')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
