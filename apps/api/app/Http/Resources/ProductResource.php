<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'cover_url' => $this->coverUrl(),
            'external_url' => $this->external_url,
            'is_published' => (bool) $this->is_published,
            'store' => $this->whenLoaded('store', fn () => [
                'slug' => $this->store->slug,
                'name' => $this->store->name,
            ]),
            // Cross-sells ("you may also like") when the offers relation is loaded.
            'cross_sells' => $this->whenLoaded('offers', function () {
                return $this->offers
                    ->where('kind', Product::OFFER_CROSS_SELL)
                    ->map(fn (ProductOffer $offer) => $offer->related)
                    ->filter(fn ($p) => $p !== null && $p->is_published)
                    ->map(fn ($p) => [
                        'ulid' => $p->ulid,
                        'title' => $p->title,
                        'price_cents' => $p->price_cents,
                        'currency' => $p->currency,
                        'cover_url' => $p->coverUrl(),
                    ])
                    ->values();
            }),
        ];
    }
}
