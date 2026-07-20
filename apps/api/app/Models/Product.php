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
 * A product sold by a store (Shop P1) — a digital download, course or service.
 */
class Product extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'digital_download',
        'course',
        'service',
    ];

    /** Offer relation kinds. */
    public const OFFER_CROSS_SELL = 'cross_sell';

    public const OFFER_BUMP = 'bump';

    public const OFFER_UPSELL = 'upsell';

    protected $fillable = [
        'ulid',
        'store_id',
        'type',
        'title',
        'description',
        'price_cents',
        'currency',
        'cover_path',
        'download_path',
        'external_url',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->ulid ??= (string) Str::ulid();
        });

        static::saving(function (Product $product) {
            if ($product->is_published && $product->published_at === null) {
                $product->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Offers hanging off this product (cross-sells / bumps / upsells). */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    /** Published, in an active store — what buyers should see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereHas('store', fn (Builder $q) => $q->where('is_active', true));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path === null ? null : Storage::disk('public')->url($this->cover_path);
    }
}
