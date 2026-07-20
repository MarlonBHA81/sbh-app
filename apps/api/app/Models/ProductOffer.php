<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cross-sell / order-bump / upsell relation between two products (Shop P1).
 */
class ProductOffer extends Model
{
    protected $fillable = [
        'product_id',
        'related_product_id',
        'kind',
        'discount_cents',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'discount_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
