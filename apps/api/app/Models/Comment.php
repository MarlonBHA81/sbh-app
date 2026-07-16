<?php

namespace App\Models;

use App\Models\Concerns\HasViewerReactionState;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasViewerReactionState, SoftDeletes;

    /** Maximum reply nesting depth (top-level comments are depth 0). */
    public const MAX_DEPTH = 3;

    public const TOMBSTONE_BODY = '[deleted]';

    protected $fillable = [
        'post_id',
        'profile_id',
        'parent_comment_id',
        'body',
        'depth',
        'helpful_at',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'upvotes_count' => 'integer',
            'downvotes_count' => 'integer',
            'likes_count' => 'integer',
            'replies_count' => 'integer',
            'helpful_at' => 'datetime',
        ];
    }

    public function isHelpful(): bool
    {
        return $this->helpful_at !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (Comment $comment) {
            $comment->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id')->orderBy('id');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /**
     * Body as presented in resources: soft-deleted comments are tombstoned
     * but their rows are kept so surviving replies remain reachable.
     */
    public function presentedBody(): string
    {
        return $this->trashed() ? self::TOMBSTONE_BODY : $this->body;
    }
}
