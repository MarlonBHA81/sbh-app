<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    public const BUCKET_LIKE = 'like';

    public const BUCKET_VOTE = 'vote';

    public const KIND_LIKE = 'like';

    public const KIND_UP = 'up';

    public const KIND_DOWN = 'down';

    protected $fillable = [
        'profile_id',
        'reactable_type',
        'reactable_id',
        'kind',
        'bucket',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
