<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A member-set goal or milestone (V3 · PROGRESS). Belongs to a single profile
 * and appears on their business dashboard. Completing a goal is self-declared —
 * we never auto-complete or invent progress for the user.
 */
class Goal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'profile_id',
        'title',
        'target',
        'due_on',
        'position',
        'is_done',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Goal $goal) {
            $goal->ulid ??= (string) Str::ulid();
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

    /** Newest goals first within a profile's list; open goals lead the view. */
    public function scopeForProfile(Builder $query, Profile $profile): Builder
    {
        return $query->where('profile_id', $profile->id);
    }
}
