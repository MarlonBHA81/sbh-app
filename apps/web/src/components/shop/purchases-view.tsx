"use client";

import { Download, ShoppingBag } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Purchase } from "@/lib/api/types";
import { downloadPurchase, productTypeLabel } from "@/lib/shop";

/** A buyer's purchases with gated downloads (Shop P2). */
export function PurchasesView() {
  const [purchases, setPurchases] = useState<Purchase[] | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Purchase[] }>("/api/v1/me/purchases")
      .then((res) => {
        if (!cancelled) setPurchases(res.data);
      })
      .catch(() => {
        if (!cancelled) setPurchases([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function onDownload(p: Purchase): Promise<void> {
    if (!p.product.ulid) return;
    setBusy(p.product.ulid);
    setError(null);
    try {
      await downloadPurchase(p.product.ulid, p.product.title ?? "download");
    } catch {
      setError("That download isn't available right now. Please try again.");
    } finally {
      setBusy(null);
    }
  }

  if (!purchases) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-20 w-full rounded-xl" />
        <Skeleton className="h-20 w-full rounded-xl" />
      </div>
    );
  }

  if (purchases.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-8 text-center shadow-card">
        <ShoppingBag className="size-8 text-text-secondary" aria-hidden />
        <p className="text-sm text-text-secondary">
          You haven&apos;t bought anything yet.
        </p>
        <Button asChild className="h-10">
          <Link href="/shop">Browse the shop</Link>
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {error ? (
        <p className="text-[12px] text-red-600" role="alert">
          {error}
        </p>
      ) : null}

      <ul className="flex flex-col gap-3">
        {purchases.map((p, i) => (
          <li
            key={p.product.ulid ?? i}
            className="flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card"
          >
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate font-medium text-text-primary">
                {p.product.title ?? "Product"}
              </span>
              <span className="text-[12px] text-text-secondary">
                {p.product.type ? productTypeLabel(p.product.type) : "Purchase"}
                {p.product.store ? ` · ${p.product.store}` : ""}
              </span>
            </div>

            {p.has_download ? (
              <Button
                type="button"
                variant="outline"
                className="h-9 shrink-0 gap-1.5"
                onClick={() => onDownload(p)}
                disabled={busy === p.product.ulid}
              >
                <Download className="size-4" aria-hidden />
                {busy === p.product.ulid ? "…" : "Download"}
              </Button>
            ) : p.product.store && p.product.ulid ? (
              <Button asChild variant="outline" className="h-9 shrink-0">
                <Link href={`/shop/${p.product.store}/${p.product.ulid}`}>
                  Open
                </Link>
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
