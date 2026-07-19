<?php

namespace App\Models;

use App\Services\SafetyService;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, SoftDeletes;

    public const KIND_PERSONAL = 'personal';

    public const KIND_BUSINESS = 'business';

    public const DM_PRIVACY_EVERYONE = 'everyone';

    public const DM_PRIVACY_FOLLOWERS = 'followers';

    public const DM_PRIVACY_NO_ONE = 'no_one';

    /**
     * Business Journey stages (V1). Where the member is in their journey;
     * drives personalisation of Home, opportunities and learning.
     */
    public const JOURNEY_STAGES = [
        'starting',
        'finding_customers',
        'growing_sales',
        'hiring',
        'expanding',
        'exporting',
    ];

    protected $fillable = [
        'user_id',
        'kind',
        'handle',
        'name',
        'bio',
        'avatar_path',
        'cover_path',
        'category',
        'journey_stage',
        'business_category_id',
        'website',
        'social_links',
        'location',
        'lat',
        'lng',
        'geohash',
        'country_code',
        'city',
        'share_location',
        'is_private',
        'dm_privacy',
        'streak_count',
        'streak_last_day',
        'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'is_private' => 'boolean',
            'is_verified' => 'boolean',
            'share_location' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
            'xp_total' => 'integer',
            'streak_count' => 'integer',
            'streak_last_day' => 'date',
            'helpful_count' => 'integer',
        ];
    }

    /**
     * Advance the engagement streak for a qualifying action today (V1).
     * Same-day repeats are a no-op; a consecutive day increments; a gap resets
     * to 1. Persists immediately.
     */
    public function advanceStreak(): void
    {
        $today = now()->toDateString();
        $last = $this->streak_last_day?->toDateString();

        if ($last === $today) {
            return;
        }

        $yesterday = now()->subDay()->toDateString();
        $this->streak_count = $last === $yesterday ? $this->streak_count + 1 : 1;
        $this->streak_last_day = $today;
        $this->save();
    }

    /**
     * Honest streak snapshot for the chip: the live count plus whether it
     * truly lapses at end of day. A streak older than yesterday reads as zero.
     *
     * @return array{current_days:int, ends_today:bool}
     */
    public function streakSnapshot(): array
    {
        $last = $this->streak_last_day?->toDateString();

        if ($last === null) {
            return ['current_days' => 0, 'ends_today' => false];
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        if ($last === $today) {
            return ['current_days' => $this->streak_count, 'ends_today' => false];
        }

        if ($last === $yesterday) {
            // Acted yesterday, not yet today — alive but at risk tonight.
            return ['current_days' => $this->streak_count, 'ends_today' => true];
        }

        return ['current_days' => 0, 'ends_today' => false];
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

    public function challenges(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Challenge::class, 'challenge_participants')
            ->withPivot('joined_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function businessNeeds(): HasMany
    {
        return $this->hasMany(BusinessNeed::class);
    }

    /**
     * Absolute URL to the avatar, or null when none is set. Used in
     * notification/actor payloads and lightweight search results.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : $this->cacheBust(Storage::disk('public')->url($this->avatar_path));
    }

    /**
     * Absolute URL to the cover image, or null when none is set.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_path === null
            ? null
            : $this->cacheBust(Storage::disk('public')->url($this->cover_path));
    }

    /** Append the profile's updated timestamp so re-uploads bust caches. */
    private function cacheBust(string $url): string
    {
        $version = $this->updated_at?->timestamp;

        return $version === null ? $url : $url.'?v='.$version;
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

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'profile_badges')
            ->withPivot('awarded_at');
    }

    public function savedOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_saves')
            ->withTimestamps();
    }

    public function savedResources(): BelongsToMany
    {
        return $this->belongsToMany(LibraryResource::class, 'resource_saves', 'profile_id', 'resource_id')
            ->withTimestamps();
    }

    public function completedLessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_completions', 'profile_id', 'lesson_id')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function xpLedger(): HasMany
    {
        return $this->hasMany(XpLedgerEntry::class);
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
     * none|following|pending|self|blocked.
     *
     * When the viewer blocked this profile we surface 'blocked'. When this
     * profile blocked the viewer we deliberately return 'none' so the block
     * is not revealed to the person who was blocked.
     */
    public function relationshipStateFor(?Profile $viewer): string
    {
        if ($viewer === null) {
            return 'none';
        }

        if ($viewer->id === $this->id) {
            return 'self';
        }

        if (app(SafetyService::class)->viewerBlocked($viewer, $this)) {
            return 'blocked';
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
        // A block in either direction hides the full profile from the viewer.
        if (app(SafetyService::class)->isBlockedBetween($viewer, $this)) {
            return false;
        }

        if (! $this->is_private) {
            return true;
        }

        if ($viewer !== null && $viewer->user_id === $this->user_id) {
            return true;
        }

        return $this->isFollowedBy($viewer);
    }
}
