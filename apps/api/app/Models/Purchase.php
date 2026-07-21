<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's entitlement to a product (Shop P2) — granted when an order is paid;
 * gates digital delivery and course access.
 */
class Purchase extends Model
{
    protected $fillable = [
        'buyer_profile_id',
        'product_id',
        'order_id',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'buyer_profile_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Whether the given profile owns (has purchased/enrolled in) the product. */
    public static function ownedBy(?Profile $profile, Product $product): bool
    {
        return $profile !== null
            && static::query()
                ->where('buyer_profile_id', $profile->id)
                ->where('product_id', $product->id)
                ->exists();
    }
}
