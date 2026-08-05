<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A single lesson within a course module (Shop P3). Content may be rich text, an
 * embedded video URL and/or a gated attachment. Preview lessons are readable
 * without a purchase; the rest require the Purchase entitlement.
 */
class CourseLesson extends Model
{
    protected $fillable = [
        'ulid',
        'course_module_id',
        'title',
        'body',
        'video_url',
        'attachment_path',
        'minutes',
        'is_preview',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'is_preview' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CourseLesson $lesson) {
            $lesson->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function completedBy(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'course_lesson_progress', 'course_lesson_id', 'profile_id')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }
}
