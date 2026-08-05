<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Mention extends Model
{
    protected $fillable = [
        'mentionable_type',
        'mentionable_id',
        'mentioned_profile_id',
        'mentioner_profile_id',
    ];

    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentioned(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'mentioned_profile_id');
    }

    public function mentioner(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'mentioner_profile_id');
    }
}
