<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;
use App\Services\Analytics\ShopStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Records that the viewer has seen a store and/or a batch of its products
 * (Shop P4 · vendor analytics). Deduped to at most one view per subject per
 * profile per UTC day, mirroring PostSeenController.
 */
class ShopSeenController extends Controller
{
    private const TTL_HOURS = 48;

    public function store(Request $request, ShopStatsService $stats): Response
    {
        $validated = $request->validate([
            'store' => ['sometimes', 'nullable', 'string'],
            'products' => ['sometimes', 'array', 'max:20'],
            'products.*' => ['string'],
        ]);

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');
        abort_unless($viewer instanceof Profile, 422, 'No active profile.');

        $date = now()->utc()->toDateString();
        $key = "shop-seen-day:{$viewer->ulid}:{$date}";
        $seen = Cache::get($key, []);
        $recorded = [];

        if (! empty($validated['store'])) {
            $token = 'store:'.$validated['store'];
            if (! in_array($token, $seen, true)) {
                $store = Store::query()->active()->where('slug', $validated['store'])->first();
                if ($store !== null) {
                    $stats->bumpStore($store);
                    $recorded[] = $token;
                }
            }
        }

        $productUlids = array_values(array_unique($validated['products'] ?? []));
        $fresh = array_diff(
            array_map(fn ($u) => 'product:'.$u, $productUlids),
            $seen,
        );

        if ($fresh !== []) {
            $ulids = array_map(fn ($t) => substr($t, strlen('product:')), $fresh);
            $products = Product::query()->visible()->whereIn('ulid', $ulids)->get();
            foreach ($products as $product) {
                $stats->bumpProduct($product);
                $recorded[] = 'product:'.$product->ulid;
            }
        }

        if ($recorded !== []) {
            Cache::put(
                $key,
                array_values(array_unique([...$seen, ...$recorded])),
                now()->addHours(self::TTL_HOURS),
            );
        }

        return response()->noContent();
    }
}
