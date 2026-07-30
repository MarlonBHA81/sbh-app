<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
     * Idempotent AND race-safe: PayFast retries ITNs, so two deliveries can
     * arrive concurrently. We re-read the row under a lock inside a transaction
     * and bail if another request already flipped it to paid — so the
     * entitlements and vendor/platform accounting are applied exactly once.
     */
    public function markPaid(): bool
    {
        // Carry any pending changes the caller set on this instance but hasn't
        // saved yet (e.g. the PayFast ITN sets pf_payment_id before calling us).
        $pending = $this->getDirty();

        return DB::transaction(function () use ($pending) {
            $fresh = static::query()->lockForUpdate()->find($this->getKey());

            if ($fresh === null || $fresh->isPaid()) {
                return false;
            }

            if ($pending !== []) {
                $fresh->forceFill($pending);
            }

            $feePercent = (float) config('payments.platform_fee_percent', 0);
            $fee = (int) round($fresh->total_cents * $feePercent / 100);

            $fresh->forceFill([
                'status' => self::STATUS_PAID,
                'paid_at' => now(),
                'platform_fee_cents' => $fee,
                'vendor_amount_cents' => $fresh->total_cents - $fee,
            ])->save();

            foreach ($fresh->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                Purchase::query()->firstOrCreate(
                    ['buyer_profile_id' => $fresh->buyer_profile_id, 'product_id' => $item->product_id],
                    ['order_id' => $fresh->id],
                );
            }

            // Keep the in-memory instance consistent for the caller.
            $this->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }
}
