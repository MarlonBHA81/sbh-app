<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public marketplace browsing (Shop P1) — active stores and their published
 * products. Buying arrives in Phase 2.
 */
class ShopController extends Controller
{
    public function stores(): AnonymousResourceCollection
    {
        $stores = Store::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->with('profile')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return StoreResource::collection($stores);
    }

    public function store(Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $store->loadCount(['products' => fn ($q) => $q->where('is_published', true)]);
        $store->load('profile');

        return response()->json(['data' => new StoreResource($store)]);
    }

    public function storeProducts(Store $store): AnonymousResourceCollection
    {
        abort_unless($store->is_active, 404);

        $products = $store->products()
            ->where('is_published', true)
            ->latest('published_at')
            ->get();

        return ProductResource::collection($products);
    }

    public function product(Product $product): JsonResponse
    {
        abort_unless(
            $product->is_published && $product->store->is_active,
            404,
        );

        $product->load('store', 'offers.related');

        return response()->json(['data' => new ProductResource($product)]);
    }
}
