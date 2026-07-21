<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A cohort-based programme (V3 · LEARN). Runs over a period with an optional
 * seat cap; members enrol into the cohort.
 */
class Masterclass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'title',
        'description',
        'facilitator_name',
        'brand_color',
        'accent_color',
        'logo_path',
        'banner_path',
        'is_sponsored',
        'sponsor_name',
        'sponsor_url',
        'sponsor_blurb',
        'starts_at',
        'ends_at',
        'capacity',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'is_published' => 'boolean',
            'is_sponsored' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Masterclass $m) {
            $m->ulid ??= (string) Str::ulid();
        });

        static::saving(function (Masterclass $m) {
            if ($m->is_published && $m->published_at === null) {
                $m->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'masterclass_participants')
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(MasterclassLiveSession::class);
    }

    /** The current (not-ended) live session, newest first. */
    public function currentLiveSession(): ?MasterclassLiveSession
    {
        return $this->liveSessions()
            ->where('status', '!=', MasterclassLiveSession::STATUS_ENDED)
            ->latest('id')
            ->first();
    }

    /** Published and not finished — what members should see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('ends_at', '>=', now());
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null
            && $this->participants()->count() >= $this->capacity;
    }

    public function seatsLeft(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->participants()->count());
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path === null ? null : Storage::disk('public')->url($this->banner_path);
    }
}
