<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    protected $fillable = [
        'blocker_profile_id',
        'blocked_profile_id',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocker_profile_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocked_profile_id');
    }
}
