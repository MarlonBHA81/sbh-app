<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer order history and vendor sales/earnings (Shop P2).
 */
class OrderController extends Controller
{
    /** The buyer's own orders. */
    public function index(Request $request): JsonResponse
    {
        $buyer = $this->activeProfile($request);

        $orders = Order::query()
            ->where('buyer_profile_id', $buyer->id)
            ->with('items')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Order $o) => $this->serialize($o));

        return response()->json(['data' => $orders]);
    }

    /** The active business profile's store sales + earnings summary. */
    public function storeOrders(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        abort_unless($profile->isBusiness() && $profile->canManageMembers($request->user()), 403);

        $store = $profile->store;
        abort_unless($store !== null, 422, 'Create your store first.');

        $paid = Order::query()->where('store_id', $store->id)->where('status', Order::STATUS_PAID);

        return response()->json([
            'data' => [
                'orders_count' => (clone $paid)->count(),
                'gross_cents' => (int) (clone $paid)->sum('total_cents'),
                'earnings_cents' => (int) (clone $paid)->sum('vendor_amount_cents'),
                'currency' => 'ZAR',
                'recent' => Order::query()->where('store_id', $store->id)
                    ->with('items')->latest()->limit(20)->get()
                    ->map(fn (Order $o) => $this->serialize($o)),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order): array
    {
        return [
            'ulid' => $order->ulid,
            'status' => $order->status,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($i) => [
                'title' => $i->title,
                'unit_cents' => $i->unit_cents,
                'kind' => $i->kind,
            ]),
        ];
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
