<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single live-streamed session of a masterclass room (ask #4). Carries the
 * host's RTMP ingest secrets and the members' HLS playback URL.
 */
class MasterclassLiveSession extends Model
{
    public const STATUS_IDLE = 'idle';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'ulid',
        'masterclass_id',
        'title',
        'status',
        'provider',
        'provider_stream_id',
        'stream_key',
        'ingest_url',
        'playback_id',
        'playback_url',
        'recording_playback_url',
        'recording_ready_at',
        'scheduled_at',
        'started_at',
        'ended_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'recording_ready_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MasterclassLiveSession $session) {
            $session->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function masterclass(): BelongsTo
    {
        return $this->belongsTo(Masterclass::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }
}
