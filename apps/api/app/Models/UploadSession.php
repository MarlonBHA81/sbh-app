<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UploadSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSEMBLING = 'assembling';

    public const STATUS_DONE = 'done';

    public const STATUS_ABORTED = 'aborted';

    protected $fillable = [
        'ulid',
        'profile_id',
        'filename',
        'mime',
        'media_type',
        'size_bytes',
        'total_chunks',
        'received_chunks',
        'status',
        'media_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UploadSession $session) {
            $session->ulid ??= (string) Str::ulid();
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

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Storage path (on the local disk) holding this session's chunk files. */
    public function chunkDirectory(): string
    {
        return 'chunks/'.$this->ulid;
    }
}
