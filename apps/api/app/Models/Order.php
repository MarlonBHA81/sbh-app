<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A marketplace order (Shop P2). Pending at checkout; marked paid by the ITN.
 */
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ulid',
        'reference',
        'buyer_profile_id',
        'store_id',
        'status',
        'total_cents',
        'currency',
        'platform_fee_cents',
        'vendor_amount_cents',
        'pf_payment_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'platform_fee_cents' => 'integer',
            'vendor_amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->ulid ??= (string) Str::ulid();
            $order->reference ??= 'SBH-'.$order->ulid;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'buyer_profile_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function itemName(): string
    {
        $first = $this->items->first();
        $extra = $this->items->count() - 1;

        return $first
            ? $first->title.($extra > 0 ? " (+{$extra} more)" : '')
            : 'SBH order';
    }

    /**
     * Mark paid, grant entitlements, and split the vendor/platform amounts.
     * Idempotent: a second call (duplicate ITN) is a no-op.
     */
    public function markPaid(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        $feePercent = (float) config('payments.platform_fee_percent', 0);
        $fee = (int) round($this->total_cents * $feePercent / 100);

        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'platform_fee_cents' => $fee,
            'vendor_amount_cents' => $this->total_cents - $fee,
        ])->save();

        foreach ($this->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            Purchase::query()->firstOrCreate(
                ['buyer_profile_id' => $this->buyer_profile_id, 'product_id' => $item->product_id],
                ['order_id' => $this->id],
            );
        }

        return true;
    }
}
