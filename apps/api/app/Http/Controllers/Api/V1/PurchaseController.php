<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A buyer's purchases + gated digital delivery (Shop P2).
 */
class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $buyer = $this->activeProfile($request);

        $purchases = Purchase::query()
            ->where('buyer_profile_id', $buyer->id)
            ->with('product.store')
            ->latest()
            ->get()
            ->map(fn (Purchase $p) => [
                'product' => [
                    'ulid' => $p->product?->ulid,
                    'title' => $p->product?->title,
                    'type' => $p->product?->type,
                    'store' => $p->product?->store?->slug,
                ],
                'has_download' => $p->product?->download_path !== null,
                'purchased_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $purchases]);
    }

    /** Stream the product's file — only when the buyer owns it. */
    public function download(Request $request, Product $product): StreamedResponse
    {
        $buyer = $this->activeProfile($request);

        $owns = Purchase::query()
            ->where('buyer_profile_id', $buyer->id)
            ->where('product_id', $product->id)
            ->exists();

        abort_unless($owns, 403, 'You don\'t own this product.');
        abort_unless($product->download_path !== null && Storage::disk('local')->exists($product->download_path), 404);

        return Storage::disk('local')->download($product->download_path, $product->title);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
