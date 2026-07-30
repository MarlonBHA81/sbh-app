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
        'is_vat_registered',
        'vat_rate_bp',
        'vat_number',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_vat_registered' => 'boolean',
            'vat_rate_bp' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** VAT rate as a fraction (0.15 for 15%) when the store is VAT registered. */
    public function vatRate(): float
    {
        return $this->is_vat_registered ? $this->vat_rate_bp / 10000 : 0.0;
    }

    /**
     * The inclusive VAT portion within a gross (VAT-inclusive) amount. Prices
     * are quoted inclusive, so VAT = gross - gross / (1 + rate).
     */
    public function inclusiveVatCents(int $grossCents): int
    {
        $rate = $this->vatRate();

        return $rate <= 0 ? 0 : (int) round($grossCents - $grossCents / (1 + $rate));
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
