<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A member's private "how are you doing?" check-in (V3 · BELONG). Only ever
 * visible to the member who logged it; never surfaced publicly, ranked or
 * rewarded.
 */
class WellnessCheckin extends Model
{
    public const MIN_MOOD = 1;

    public const MAX_MOOD = 5;

    protected $fillable = [
        'ulid',
        'profile_id',
        'mood',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'mood' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WellnessCheckin $checkin) {
            $checkin->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
