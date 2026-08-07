<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BusinessVerificationDocument extends Model
{
    protected $fillable = [
        'business_verification_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
    ];

    protected static function booted(): void
    {
        static::creating(function (BusinessVerificationDocument $document) {
            $document->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(BusinessVerification::class, 'business_verification_id');
    }
}
