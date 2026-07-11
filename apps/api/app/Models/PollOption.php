<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollOption extends Model
{
    protected $fillable = [
        'poll_id',
        'label',
        'votes_count',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }
}
