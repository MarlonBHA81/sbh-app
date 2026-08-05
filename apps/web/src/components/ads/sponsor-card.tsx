"use client";

import { ExternalLink, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { ExternalLink as OutboundLink } from "@/components/ui/external-link";
import * as api from "@/lib/api/client";
import { trackSlotClick, trackSlotImpression } from "@/lib/ads/track";
import type { AdSlot } from "@/lib/api/types";
import { cn } from "@/lib/utils";

type Placement = "right_rail" | "feed_inline";

/** Placements the viewer dismissed this session (feed inline only). */
const dismissedPlacements = new Set<Placement>();

type SlotState =
  | { phase: "loading" }
  | { phase: "empty" }
  | { phase: "ready"; slot: AdSlot };

/**
 * Fetch a sponsor slot once per mount. `slot` is `null` while loading or when
 * there is nothing to show (204, error, or dismissed). `status` lets callers
 * distinguish genuinely unsold inventory ("empty" — show an ad-spot
 * placeholder) from loading or a viewer dismissal (show nothing).
 */
export function useSponsorSlot(
  placement: Placement,
  { enabled = true }: { enabled?: boolean } = {},
): {
  slot: AdSlot | null;
  status: "loading" | "empty" | "ready" | "dismissed";
  dismiss: () => void;
} {
  const [state, setState] = useState<SlotState>(() =>
    !enabled || dismissedPlacements.has(placement)
      ? { phase: "empty" }
      : { phase: "loading" },
  );
  const [dismissed, setDismissed] = useState(() =>
    dismissedPlacements.has(placement),
  );

  useEffect(() => {
    if (!enabled || dismissedPlacements.has(placement)) return;
    let cancelled = false;
    api
      .get<{ data: AdSlot } | null>(`/api/v1/ads/slots/${placement}`)
      .then((res) => {
        if (cancelled) return;
        setState(
          res?.data ? { phase: "ready", slot: res.data } : { phase: "empty" },
        );
      })
      .catch(() => {
        if (!cancelled) setState({ phase: "empty" });
      });
    return () => {
      cancelled = true;
    };
  }, [placement, enabled]);

  const dismiss = () => {
    dismissedPlacements.add(placement);
    setDismissed(true);
  };

  return {
    slot: !dismissed && state.phase === "ready" ? state.slot : null,
    status: dismissed ? "dismissed" : state.phase,
    dismiss,
  };
}

/** Presentational sponsor unit with impression + click tracking. */
export function SponsorCard({
  slot,
  variant = "rail",
  onDismiss,
}: {
  slot: AdSlot;
  variant?: "rail" | "inline";
  onDismiss?: () => void;
}) {
  const t = useTranslations("feed");
  const ref = useRef<HTMLElement | null>(null);

  // Impression: fire once the unit is actually on screen.
  useEffect(() => {
    const node = ref.current;
    if (!node || typeof IntersectionObserver === "undefined") {
      trackSlotImpression(slot.key);
      return;
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          trackSlotImpression(slot.key);
          observer.disconnect();
        }
      },
      { threshold: 0.5 },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [slot.key]);

  return (
    <aside
      ref={ref}
      aria-label={t("sponsored")}
      className={cn(
        "relative flex flex-col gap-3 rounded-xl border bg-card p-3 text-card-foreground",
        variant === "inline" && "shadow-xs",
      )}
    >
      {onDismiss ? (
        <button
          type="button"
          aria-label="Dismiss sponsored post"
          onClick={onDismiss}
          className="absolute top-2 right-2 z-10 flex size-7 items-center justify-center rounded-full bg-background/70 text-muted-foreground backdrop-blur transition-colors hover:bg-accent hover:text-foreground"
        >
          <X className="size-4" aria-hidden />
        </button>
      ) : null}

      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={slot.image_url}
        alt=""
        className="aspect-video w-full rounded-lg object-cover"
        loading="lazy"
      />

      <div className="flex flex-col gap-1">
        <div className="flex items-center gap-2">
          <span className="truncate text-sm font-semibold">
            {slot.sponsor_name}
          </span>
          <span className="shrink-0 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
            {t("sponsored")}
          </span>
        </div>
        <p className="line-clamp-2 text-sm text-muted-foreground">
          {slot.body}
        </p>
      </div>

      <Button asChild variant="outline" size="sm" className="w-full">
        <OutboundLink
          href={slot.sponsor_url}
          rel="sponsored noopener noreferrer"
          onClick={() => trackSlotClick(slot.key)}
        >
          Visit
          <ExternalLink className="size-4" aria-hidden />
        </OutboundLink>
      </Button>
    </aside>
  );
}
