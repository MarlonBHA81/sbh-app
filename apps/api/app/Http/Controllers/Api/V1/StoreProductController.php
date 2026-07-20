<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Vendor product management (Shop P1) — CRUD for the active business profile's
 * store, gated to its owner/managers.
 */
class StoreProductController extends Controller
{
    /** All products (including drafts) for the vendor's store. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $store = $this->vendorStore($request);

        return ProductResource::collection($store->products()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        $data = $this->validated($request);

        $product = $store->products()->create($data);

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $store = $this->vendorStore($request);
        abort_unless($product->store_id === $store->id, 403);

        $product->update($this->validated($request, updating: true));

        return response()->json(['data' => new ProductResource($product->fresh())]);
    }

    public function destroy(Request $request, Product $product): Response
    {
        $store = $this->vendorStore($request);
        abort_unless($product->store_id === $store->id, 403);

        $product->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$required, Rule::in(Product::TYPES)],
            'title' => [$required, 'string', 'max:160'],
            'description' => [$required, 'string', 'max:5000'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }

    private function vendorStore(Request $request): Store
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 403, 'Only business profiles can run a store.');
        abort_unless($profile->canManageMembers($request->user()), 403);

        $store = $profile->store;
        abort_unless($store !== null, 422, 'Create your store first.');

        return $store;
    }
}
