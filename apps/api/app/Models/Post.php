<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FOLLOWERS = 'followers';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_REPOST = 'repost';

    public const TYPE_MAGNIFIER = 'magnifier';

    public const TYPE_SECRET = 'secret';

    protected $fillable = [
        'profile_id',
        'type',
        'body',
        'payload',
        'visibility',
        'status',
        'scheduled_at',
        'published_at',
        'lat',
        'lng',
        'geohash',
        'country_code',
        'city',
        'sensitive',
        'parent_post_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sensitive' => 'boolean',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'reposts_count' => 'integer',
            'views_count' => 'integer',
            'upvotes_count' => 'integer',
            'downvotes_count' => 'integer',
            'score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->ulid ??= (string) Str::ulid();
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_post_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_post_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'post_topic');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isRepost(): bool
    {
        return $this->type === self::TYPE_REPOST;
    }

    /**
     * Whether the given user owns (authored) this post through any profile.
     */
    public function isAuthoredBy(?User $user): bool
    {
        return $user !== null && $this->profile->user_id === $user->id;
    }

    /**
     * Whether the given viewer profile may see this post.
     *
     * The author (any profile of the owning user) always sees their own
     * posts, including drafts and scheduled ones. Everyone else only sees
     * published posts, subject to profile privacy and post visibility.
     */
    public function isVisibleTo(?Profile $viewer): bool
    {
        $author = $this->profile;

        if ($viewer !== null && $viewer->user_id === $author->user_id) {
            return true;
        }

        if (! $this->isPublished()) {
            return false;
        }

        if ($author->is_private && ! $author->isFollowedBy($viewer)) {
            return false;
        }

        if ($this->visibility === self::VISIBILITY_FOLLOWERS && ! $author->isFollowedBy($viewer)) {
            return false;
        }

        return true;
    }
}
