<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Challenge extends Model
{
    protected $fillable = [
        'ulid',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Challenge $challenge) {
            $challenge->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'challenge_participants')
            ->withPivot('joined_at');
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    /** Active challenges, soonest-ending first, then upcoming, then past. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
