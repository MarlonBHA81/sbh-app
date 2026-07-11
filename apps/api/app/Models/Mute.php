<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mute extends Model
{
    protected $fillable = [
        'muter_profile_id',
        'muted_profile_id',
    ];

    public function muter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'muter_profile_id');
    }

    public function muted(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'muted_profile_id');
    }
}
