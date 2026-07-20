<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A vendor storefront (Shop P1). One per business profile; branded for the shop
 * owner and managed by the profile's owner/managers.
 */
class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'profile_id',
        'slug',
        'name',
        'tagline',
        'about',
        'brand_color',
        'accent_color',
        'logo_path',
        'banner_path',
        'policies',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            $store->ulid ??= (string) Str::ulid();
            $store->published_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path === null ? null : Storage::disk('public')->url($this->banner_path);
    }
}
