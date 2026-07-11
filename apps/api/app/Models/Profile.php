<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, SoftDeletes;

    public const KIND_PERSONAL = 'personal';

    public const KIND_BUSINESS = 'business';

    protected $fillable = [
        'user_id',
        'kind',
        'handle',
        'name',
        'bio',
        'avatar_path',
        'cover_path',
        'category',
        'website',
        'location',
        'lat',
        'lng',
        'geohash',
        'country_code',
        'city',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_verified' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
            'xp_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Profile $profile) {
            $profile->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function handle(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtolower($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_profile_id', 'follower_profile_id')
            ->withPivot('state')
            ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_profile_id', 'followed_profile_id')
            ->withPivot('state')
            ->withTimestamps();
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'profile_badges')
            ->withPivot('awarded_at');
    }

    public function scopePersonal(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_PERSONAL);
    }

    public function scopeBusiness(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_BUSINESS);
    }

    public function isPersonal(): bool
    {
        return $this->kind === self::KIND_PERSONAL;
    }

    public function isBusiness(): bool
    {
        return $this->kind === self::KIND_BUSINESS;
    }

    public function isFollowedBy(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return Follow::query()
            ->where('follower_profile_id', $profile->id)
            ->where('followed_profile_id', $this->id)
            ->where('state', Follow::STATE_ACCEPTED)
            ->exists();
    }

    /**
     * Relationship state of the given viewer profile toward this profile:
     * none|following|pending|self.
     */
    public function relationshipStateFor(?Profile $viewer): string
    {
        if ($viewer === null) {
            return 'none';
        }

        if ($viewer->id === $this->id) {
            return 'self';
        }

        $state = Follow::query()
            ->where('follower_profile_id', $viewer->id)
            ->where('followed_profile_id', $this->id)
            ->value('state');

        return match ($state) {
            Follow::STATE_ACCEPTED => 'following',
            Follow::STATE_PENDING => 'pending',
            default => 'none',
        };
    }

    /**
     * Whether the given viewer profile may see this profile's full details.
     */
    public function isViewableBy(?Profile $viewer): bool
    {
        if (! $this->is_private) {
            return true;
        }

        if ($viewer !== null && $viewer->user_id === $this->user_id) {
            return true;
        }

        return $this->isFollowedBy($viewer);
    }
}
