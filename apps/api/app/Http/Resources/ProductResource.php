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
            'sale_price_cents' => $this->when($this->onSale(), fn () => (int) $this->sale_price_cents),
            'effective_price_cents' => $this->effectivePriceCents(),
            'on_sale' => $this->onSale(),
            'currency' => $this->currency,
            'cover_url' => $this->coverUrl(),
            'external_url' => $this->external_url,
            'is_published' => (bool) $this->is_published,
            'has_file' => $this->download_path !== null,
            'is_html_tool' => $this->isHtmlTool(),
            'is_free' => $this->isFree(),
            // Curriculum summary for course products (when modules are loaded).
            'course' => $this->when($this->isCourse(), fn () => [
                'modules_count' => $this->whenLoaded('modules', fn () => $this->modules->count()),
                'lessons_count' => $this->whenLoaded('modules', fn () => $this->modules->sum(fn ($m) => $m->lessons->count())),
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'slug' => $this->store->slug,
                'name' => $this->store->name,
                'vat_registered' => (bool) $this->store->is_vat_registered,
                'vat_rate_bp' => $this->store->is_vat_registered ? (int) $this->store->vat_rate_bp : 0,
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
            // Order bumps offered at checkout (with the bump price after discount).
            'bumps' => $this->whenLoaded('offers', function () {
                return $this->offers
                    ->where('kind', Product::OFFER_BUMP)
                    ->filter(fn (ProductOffer $offer) => $offer->related !== null && $offer->related->is_published)
                    ->map(fn (ProductOffer $offer) => [
                        'ulid' => $offer->related->ulid,
                        'title' => $offer->related->title,
                        'price_cents' => max(0, (int) $offer->related->effectivePriceCents() - (int) ($offer->discount_cents ?? 0)),
                        'original_price_cents' => $offer->related->price_cents,
                        'currency' => $offer->related->currency,
                    ])
                    ->values();
            }),
            // Post-purchase upsells ("add this too") surfaced after checkout.
            'upsells' => $this->whenLoaded('offers', function () {
                return $this->offers
                    ->where('kind', Product::OFFER_UPSELL)
                    ->filter(fn (ProductOffer $offer) => $offer->related !== null && $offer->related->is_published)
                    ->map(fn (ProductOffer $offer) => [
                        'ulid' => $offer->related->ulid,
                        'title' => $offer->related->title,
                        'price_cents' => max(0, (int) $offer->related->effectivePriceCents() - (int) ($offer->discount_cents ?? 0)),
                        'original_price_cents' => $offer->related->price_cents,
                        'currency' => $offer->related->currency,
                        'cover_url' => $offer->related->coverUrl(),
                    ])
                    ->values();
            }),
        ];
    }
}
