"use client";

import { ChevronRight, Store as StoreIcon } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { ScreenHeader } from "@/components/shell/screen-header";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Store } from "@/lib/api/types";

/** Marketplace home (Shop P1): browse vendor storefronts. */
export function ShopView() {
  const [stores, setStores] = useState<Store[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Store[] }>("/api/v1/shop/stores")
      .then((res) => {
        if (!cancelled) setStores(res.data);
      })
      .catch(() => {
        if (!cancelled) setStores([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Shop" />
      <p className="text-sm text-text-secondary">
        Courses, digital downloads and tools from businesses in the community.
      </p>

      {stores === null ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-24 w-full rounded-xl" />
          <Skeleton className="h-24 w-full rounded-xl" />
        </div>
      ) : stores.length === 0 ? (
        <div className="rounded-(--radius-card) border border-warmgray bg-card p-6 text-center text-sm text-text-secondary shadow-card">
          No stores yet — be the first to open one from your business profile.
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {stores.map((s) => (
            <li key={s.ulid}>
              <Link
                href={`/shop/${s.slug}`}
                className="flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card active:scale-[0.99]"
              >
                <span
                  className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full text-white"
                  style={{ background: s.brand_color ?? "var(--color-teal)" }}
                >
                  {s.logo_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={s.logo_url}
                      alt=""
                      className="size-full object-cover"
                    />
                  ) : (
                    <StoreIcon className="size-5" aria-hidden />
                  )}
                </span>
                <span className="flex min-w-0 flex-1 flex-col">
                  <span className="truncate font-heading text-[15px] font-semibold text-text-primary">
                    {s.name}
                  </span>
                  {s.tagline ? (
                    <span className="truncate text-[13px] text-text-secondary">
                      {s.tagline}
                    </span>
                  ) : null}
                  {typeof s.products_count === "number" ? (
                    <span className="text-[11px] text-text-secondary">
                      {s.products_count} product
                      {s.products_count === 1 ? "" : "s"}
                    </span>
                  ) : null}
                </span>
                <ChevronRight
                  className="size-5 shrink-0 text-text-secondary"
                  aria-hidden
                />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
