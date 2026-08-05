<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Report extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** Statuses that count as an unresolved (open) report for dedupe. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_REVIEWING];

    public const CATEGORIES = [
        'spam', 'harassment', 'hate_speech', 'violence',
        'misinformation', 'nudity', 'scam', 'other',
    ];

    protected $fillable = [
        'reporter_profile_id',
        'reportable_type',
        'reportable_id',
        'category',
        'details',
        'status',
        'handled_by',
        'resolution_note',
        'ai_assessment',
    ];

    protected function casts(): array
    {
        return [
            'ai_assessment' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            $report->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reporter_profile_id');
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
