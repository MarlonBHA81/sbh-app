<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const KIND_ITEM = 'item';

    public const KIND_BUMP = 'bump';

    public const KIND_UPSELL = 'upsell';

    protected $fillable = [
        'order_id',
        'product_id',
        'title',
        'unit_cents',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'unit_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
