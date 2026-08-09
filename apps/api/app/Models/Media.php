<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** Rejected by the virus scanner; the file has been removed from storage. */
    public const STATUS_INFECTED = 'infected';

    protected $table = 'media';

    protected $fillable = [
        'ulid',
        'profile_id',
        'type',
        'disk',
        'path',
        'thumb_path',
        'width',
        'height',
        'duration_seconds',
        'size_bytes',
        'mime',
        'status',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $media->ulid ??= (string) Str::ulid();
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

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isAttached(): bool
    {
        return $this->mediable_id !== null;
    }

    public function url(): ?string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function thumbUrl(): ?string
    {
        return $this->thumb_path === null
            ? null
            : Storage::disk($this->disk)->url($this->thumb_path);
    }
}
