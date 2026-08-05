"use client";

import { CheckCircle2, Clock, Plus, XCircle } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { Order, Product } from "@/lib/api/types";
import { formatPrice, startCheckout } from "@/lib/shop";

/**
 * Post-redirect checkout screen (Shop P2). PayFast returns the buyer here after
 * the return_url; the ITN confirms the payment server-side, so we poll the order
 * until it flips to paid. Purely cosmetic — the entitlement is granted by the ITN.
 */
export function CheckoutResultView({
  outcome,
  orderUlid,
}: {
  outcome: "success" | "cancel";
  orderUlid: string | null;
}) {
  const [status, setStatus] = useState<Order["status"] | "unknown">(
    outcome === "cancel" ? "cancelled" : "pending",
  );
  const [upsells, setUpsells] = useState<
    NonNullable<Product["upsells"]>
  >([]);
  const attempts = useRef(0);

  useEffect(() => {
    if (outcome !== "success" || !orderUlid) return;
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;

    const poll = async () => {
      try {
        const res = await api.get<{ data: Order }>(
          `/api/v1/shop/orders/${orderUlid}`,
        );
        if (cancelled) return;
        setStatus(res.data.status);
        if (res.data.status === "pending" && attempts.current < 15) {
          attempts.current += 1;
          timer = setTimeout(poll, 2000);
        }
        // Once paid, offer the primary product's upsells ("add this too").
        if (res.data.status === "paid" && res.data.product_ulid) {
          api
            .get<{ data: Product }>(
              `/api/v1/shop/products/${res.data.product_ulid}`,
            )
            .then((p) => {
              if (!cancelled) setUpsells(p.data.upsells ?? []);
            })
            .catch(() => {});
        }
      } catch {
        if (!cancelled) setStatus("unknown");
      }
    };
    poll();

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [outcome, orderUlid]);

  if (outcome === "cancel") {
    return (
      <Panel
        icon={<XCircle className="size-10 text-text-secondary" aria-hidden />}
        title="Checkout cancelled"
        body="No payment was taken. Your order wasn't placed."
      >
        <Button asChild className="h-11">
          <Link href="/shop">Back to shop</Link>
        </Button>
      </Panel>
    );
  }

  if (status === "paid") {
    return (
      <Panel
        icon={<CheckCircle2 className="size-10 text-teal" aria-hidden />}
        title="Payment confirmed"
        body="Thanks for your purchase! Your downloads and access are ready."
      >
        <Button asChild className="h-11">
          <Link href="/shop/purchases">View my purchases</Link>
        </Button>
        <Button asChild variant="outline" className="h-11">
          <Link href="/shop">Keep shopping</Link>
        </Button>
        {upsells.length > 0 ? (
          <div className="mt-2 flex flex-col gap-2 border-t border-warmgray pt-3 text-start">
            <p className="text-[13px] font-semibold text-text-primary">
              Add this too
            </p>
            {upsells.map((u) => (
              <button
                key={u.ulid}
                type="button"
                onClick={() => void startCheckout(u.ulid)}
                className="flex items-center gap-2 rounded-lg border border-warmgray bg-card px-3 py-2 text-start transition-colors hover:bg-sage/10"
              >
                <Plus className="size-4 shrink-0 text-teal-text" aria-hidden />
                <span className="flex-1 text-sm text-text-primary">
                  {u.title}
                </span>
                <span className="text-sm font-semibold text-teal-text">
                  {formatPrice(u.price_cents, u.currency)}
                </span>
              </button>
            ))}
          </div>
        ) : null}
      </Panel>
    );
  }

  if (status === "failed" || status === "cancelled" || status === "unknown") {
    return (
      <Panel
        icon={<XCircle className="size-10 text-text-secondary" aria-hidden />}
        title="Payment not completed"
        body="We couldn't confirm this payment. If you were charged, it will appear in your purchases shortly."
      >
        <Button asChild className="h-11">
          <Link href="/shop/purchases">Check my purchases</Link>
        </Button>
        <Button asChild variant="outline" className="h-11">
          <Link href="/shop">Back to shop</Link>
        </Button>
      </Panel>
    );
  }

  return (
    <Panel
      icon={<Clock className="size-10 animate-pulse text-teal" aria-hidden />}
      title="Confirming your payment…"
      body="This usually takes a few seconds. You can safely leave this page — your access will be ready in My purchases."
    >
      <Button asChild variant="outline" className="h-11">
        <Link href="/shop/purchases">Go to my purchases</Link>
      </Button>
    </Panel>
  );
}

function Panel({
  icon,
  title,
  body,
  children,
}: {
  icon: React.ReactNode;
  title: string;
  body: string;
  children: React.ReactNode;
}) {
  return (
    <div className="mx-auto flex max-w-sm flex-col items-center gap-4 rounded-(--radius-card) border border-warmgray bg-card p-6 text-center shadow-card">
      {icon}
      <h1 className="font-heading text-xl font-semibold text-text-primary">
        {title}
      </h1>
      <p className="text-sm leading-relaxed text-text-secondary">{body}</p>
      <div className="flex w-full flex-col gap-2">{children}</div>
    </div>
  );
}
