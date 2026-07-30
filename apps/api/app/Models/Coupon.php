<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A discount code applied at checkout. Platform-wide when store_id is null,
 * otherwise scoped to a single store.
 */
class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'ulid',
        'code',
        'store_id',
        'type',
        'value',
        'min_spend_cents',
        'max_redemptions',
        'redeemed_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_spend_cents' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon) {
            $coupon->ulid ??= (string) Str::ulid();
        });

        static::saving(function (Coupon $coupon) {
            $coupon->code = strtoupper(trim($coupon->code));
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

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether the coupon is live right now (active + within its window). */
    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return $this->max_redemptions === null
            || $this->redeemed_count < $this->max_redemptions;
    }

    /** Whether this coupon may be applied to an order for the given store. */
    public function appliesToStore(int $storeId): bool
    {
        return $this->store_id === null || $this->store_id === $storeId;
    }

    /**
     * The discount in cents this coupon yields against a subtotal, clamped so it
     * never exceeds the subtotal. Returns 0 when the min-spend isn't met.
     */
    public function discountFor(int $subtotalCents): int
    {
        if ($this->min_spend_cents !== null && $subtotalCents < $this->min_spend_cents) {
            return 0;
        }

        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotalCents * min(100, max(0, $this->value)) / 100)
            : (int) $this->value;

        return max(0, min($discount, $subtotalCents));
    }
}
