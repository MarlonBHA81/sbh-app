"use client";

import { ArrowLeft, Store as StoreIcon } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Product, Store } from "@/lib/api/types";
import { formatPrice, productTypeLabel, trackShopView } from "@/lib/shop";

/** A vendor's branded storefront (Shop P1). */
export function StorefrontView({ slug }: { slug: string }) {
  const [store, setStore] = useState<Store | null>(null);
  const [products, setProducts] = useState<Product[] | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const s = (await api.get<{ data: Store }>(`/api/v1/shop/stores/${slug}`))
          .data;
        const p = (
          await api.get<{ data: Product[] }>(
            `/api/v1/shop/stores/${slug}/products`,
          )
        ).data;
        if (!cancelled) {
          setStore(s);
          setProducts(p);
          trackShopView({ store: slug, products: p.slice(0, 20).map((x) => x.ulid) });
        }
      } catch {
        if (!cancelled) setMissing(true);
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (missing) {
    return (
      <div className="flex flex-col gap-4">
        <BackLink />
        <p className="text-sm text-text-secondary">This store isn&apos;t available.</p>
      </div>
    );
  }

  if (!store) {
    return (
      <div className="flex flex-col gap-4">
        <BackLink />
        <Skeleton className="h-40 w-full rounded-xl" />
      </div>
    );
  }

  const brand = store.brand_color ?? "var(--color-teal)";

  return (
    <div className="flex flex-col gap-4">
      <BackLink />

      <section className="overflow-hidden rounded-(--radius-card) border border-warmgray bg-card shadow-card">
        <div
          className="h-24 w-full"
          style={{
            background: store.banner_url
              ? `center/cover url(${store.banner_url})`
              : `linear-gradient(135deg, ${brand}, ${store.accent_color ?? brand})`,
          }}
        />
        <div className="flex items-center gap-3 p-4">
          <span
            className="-mt-10 flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-card text-white"
            style={{ background: brand }}
          >
            {store.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={store.logo_url} alt="" className="size-full object-cover" />
            ) : (
              <StoreIcon className="size-6" aria-hidden />
            )}
          </span>
          <div className="flex min-w-0 flex-1 flex-col">
            <h1 className="truncate font-heading text-lg font-semibold text-text-primary">
              {store.name}
            </h1>
            {store.tagline ? (
              <p className="truncate text-sm text-text-secondary">
                {store.tagline}
              </p>
            ) : null}
          </div>
        </div>
        {store.about ? (
          <p className="px-4 pb-4 text-[13px] leading-snug text-text-secondary">
            {store.about}
          </p>
        ) : null}
      </section>

      {products && products.length > 0 ? (
        <ul className="grid grid-cols-2 gap-3">
          {products.map((p) => (
            <li key={p.ulid}>
              <Link
                href={`/shop/${store.slug}/${p.ulid}`}
                className="flex h-full flex-col gap-2 rounded-(--radius-card) border border-warmgray bg-card p-3 shadow-card active:scale-[0.99]"
              >
                <div
                  className="flex h-24 items-center justify-center rounded-lg text-white"
                  style={{
                    background: p.cover_url
                      ? `center/cover url(${p.cover_url})`
                      : brand,
                  }}
                >
                  {!p.cover_url ? (
                    <span className="text-xs font-medium opacity-80">
                      {productTypeLabel(p.type)}
                    </span>
                  ) : null}
                </div>
                <span className="line-clamp-2 text-sm font-medium text-text-primary">
                  {p.title}
                </span>
                <span className="mt-auto text-sm font-semibold" style={{ color: brand }}>
                  {formatPrice(p.price_cents, p.currency)}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-text-secondary">
          No products listed yet.
        </p>
      )}
    </div>
  );
}

function BackLink() {
  return (
    <Link
      href="/shop"
      className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden />
      All stores
    </Link>
  );
}
