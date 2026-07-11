<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'description',
        'icon',
        'path',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'followers_count' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Topic $topic) {
            if ($topic->path === null) {
                $parent = $topic->parent_id !== null ? self::query()->find($topic->parent_id) : null;

                $topic->path = $parent ? $parent->path.'/'.$topic->slug : $topic->slug;
                $topic->depth = $parent ? $parent->depth + 1 : 0;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'topic_follows')->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_topic');
    }

    /**
     * Ids of this topic and every descendant (via the materialized path).
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        return self::query()
            ->where('id', $this->id)
            ->orWhere('path', 'like', $this->path.'/%')
            ->pluck('id')
            ->all();
    }

    public function isFollowedBy(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return TopicFollow::query()
            ->where('profile_id', $profile->id)
            ->where('topic_id', $this->id)
            ->exists();
    }
}
