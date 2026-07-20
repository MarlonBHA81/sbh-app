"use client";

import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Product } from "@/lib/api/types";
import { formatPrice, productTypeLabel } from "@/lib/shop";

/** Product detail (Shop P1). "Buy" is disabled until checkout lands (Phase 2). */
export function ProductView({ slug, ulid }: { slug: string; ulid: string }) {
  const [product, setProduct] = useState<Product | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Product }>(`/api/v1/shop/products/${ulid}`)
      .then((res) => {
        if (!cancelled) setProduct(res.data);
      })
      .catch(() => {
        if (!cancelled) setMissing(true);
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  if (missing) {
    return (
      <div className="flex flex-col gap-4">
        <BackLink slug={slug} />
        <p className="text-sm text-text-secondary">
          This product isn&apos;t available.
        </p>
      </div>
    );
  }

  if (!product) {
    return (
      <div className="flex flex-col gap-4">
        <BackLink slug={slug} />
        <Skeleton className="h-64 w-full rounded-xl" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <BackLink slug={slug} />

      <section className="flex flex-col gap-4 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
        {product.cover_url ? (
          <div
            className="h-44 w-full rounded-lg"
            style={{ background: `center/cover url(${product.cover_url})` }}
          />
        ) : null}

        <div className="flex flex-col gap-1">
          <span className="w-fit rounded-full bg-teal/12 px-2 py-0.5 text-[11px] font-medium text-teal-text">
            {productTypeLabel(product.type)}
          </span>
          <h1 className="font-heading text-xl font-semibold text-text-primary">
            {product.title}
          </h1>
          <span className="text-lg font-semibold text-text-primary">
            {formatPrice(product.price_cents, product.currency)}
          </span>
        </div>

        <p className="text-sm leading-relaxed whitespace-pre-wrap text-text-secondary">
          {product.description}
        </p>

        <Button type="button" className="h-11" disabled>
          Buy — coming soon
        </Button>
        <p className="text-center text-[11px] text-text-secondary">
          Checkout opens shortly. Meanwhile, contact the store to enquire.
        </p>
      </section>

      {product.cross_sells && product.cross_sells.length > 0 ? (
        <section className="flex flex-col gap-2">
          <h2 className="font-heading text-[15px] font-semibold text-text-primary">
            You may also like
          </h2>
          <ul className="grid grid-cols-2 gap-3">
            {product.cross_sells.map((c) => (
              <li key={c.ulid}>
                <Link
                  href={`/shop/${slug}/${c.ulid}`}
                  className="flex h-full flex-col gap-2 rounded-(--radius-card) border border-warmgray bg-card p-3 shadow-card active:scale-[0.99]"
                >
                  <div
                    className="h-20 rounded-lg bg-sage/20"
                    style={
                      c.cover_url
                        ? { background: `center/cover url(${c.cover_url})` }
                        : undefined
                    }
                  />
                  <span className="line-clamp-2 text-sm font-medium text-text-primary">
                    {c.title}
                  </span>
                  <span className="mt-auto text-sm font-semibold text-teal-text">
                    {formatPrice(c.price_cents, c.currency)}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}

function BackLink({ slug }: { slug: string }) {
  return (
    <Link
      href={`/shop/${slug}`}
      className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden />
      Back to store
    </Link>
  );
}
