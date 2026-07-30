<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BugReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_TRIAGED = 'triaged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** Statuses that still need someone to look at them. */
    public const OPEN_STATUSES = [self::STATUS_OPEN, self::STATUS_TRIAGED];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected $fillable = [
        'user_id',
        'profile_id',
        'summary',
        'details',
        'url',
        'user_agent',
        'app_version',
        'status',
        'handled_by',
        'resolution_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (BugReport $report) {
            $report->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
