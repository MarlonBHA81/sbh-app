<?php

namespace App\Models;

use Database\Factories\RankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tier a profile reaches once its xp_total crosses min_xp.
 */
class Rank extends Model
{
    /** @use HasFactory<RankFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'min_xp',
        'icon',
        'position',
        'badge_id',
    ];

    protected function casts(): array
    {
        return [
            'min_xp' => 'integer',
            'position' => 'integer',
        ];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
