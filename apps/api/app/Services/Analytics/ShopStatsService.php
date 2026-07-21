<?php

namespace App\Services\Analytics;

use App\Models\Product;
use App\Models\ProductStatsDaily;
use App\Models\Store;
use App\Models\StoreStatsDaily;
use Illuminate\Support\Facades\DB;

/**
 * Maintains per-day view rollups for stores and products (Shop P4). Mirrors
 * PostStatsService: a portable upsert-increment of today's UTC-day row. Views
 * are deduped upstream (the "seen" endpoint), so every call here counts.
 */
class ShopStatsService
{
    public function bumpStore(Store $store, int $by = 1): void
    {
        if ($by === 0) {
            return;
        }

        $date = now()->utc()->toDateString();

        StoreStatsDaily::query()->insertOrIgnore(['store_id' => $store->id, 'date' => $date]);
        StoreStatsDaily::query()
            ->where('store_id', $store->id)->where('date', $date)
            ->update(['views' => DB::raw('views + '.(int) $by)]);
    }

    public function bumpProduct(Product $product, int $by = 1): void
    {
        if ($by === 0) {
            return;
        }

        $date = now()->utc()->toDateString();

        ProductStatsDaily::query()->insertOrIgnore(['product_id' => $product->id, 'date' => $date]);
        ProductStatsDaily::query()
            ->where('product_id', $product->id)->where('date', $date)
            ->update(['views' => DB::raw('views + '.(int) $by)]);
    }
}
