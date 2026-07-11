<?php

namespace App\Models;

use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory;

    public const STATE_ACCEPTED = 'accepted';

    public const STATE_PENDING = 'pending';

    protected $fillable = [
        'follower_profile_id',
        'followed_profile_id',
        'state',
    ];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'follower_profile_id');
    }

    public function followed(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'followed_profile_id');
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }
}
