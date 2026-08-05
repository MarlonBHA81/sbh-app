<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    public const RSVP_GOING = 'going';

    public const RSVP_INTERESTED = 'interested';

    public const RSVP_NONE = 'none';

    /** Transient: the current viewer's RSVP status (hydrated per request), or null. */
    public ?string $viewerRsvp = null;

    protected $fillable = [
        'post_id',
        'title',
        'starts_at',
        'ends_at',
        'venue',
        'going_count',
        'interested_count',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'going_count' => 'integer',
            'interested_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }
}
