<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached personalised headline for one member on one day (V2 · Feature 7).
 * Keeps the Daily Business Brief's AI call to at most once per member per day.
 */
class DailyBrief extends Model
{
    protected $fillable = [
        'profile_id',
        'brief_date',
        'headline',
        'item_ulids',
    ];

    protected function casts(): array
    {
        return [
            'brief_date' => 'date',
            'item_ulids' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
