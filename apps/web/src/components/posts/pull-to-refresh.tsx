"use client";

import { RefreshCw } from "lucide-react";
import { useTranslations } from "next-intl";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  useSyncExternalStore,
} from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/** Asymptotic cap for the rubber-band pull distance (px). */
const MAX_PULL = 140;
/** Release past this distance triggers a refresh (px). */
const THRESHOLD = 72;

function useMediaQuery(query: string): boolean {
  const subscribe = useCallback(
    (callback: () => void) => {
      const mql = window.matchMedia(query);
      mql.addEventListener("change", callback);
      return () => mql.removeEventListener("change", callback);
    },
    [query],
  );
  return useSyncExternalStore(
    subscribe,
    () => window.matchMedia(query).matches,
    () => false,
  );
}

function atTop(): boolean {
  return (document.scrollingElement?.scrollTop ?? window.scrollY) <= 0;
}

/**
 * Touch pull-to-refresh wrapper. Drags the content down with rubber-band
 * damping while the page is scrolled to the top; releasing past the
 * threshold runs `onRefresh` (page 1 refetch) and snaps back. Desktop
 * (fine pointers) gets a subtle refresh button instead of the gesture.
 */
export function PullToRefresh({
  onRefresh,
  children,
  className,
}: {
  onRefresh: () => Promise<void> | void;
  children: React.ReactNode;
  className?: string;
}) {
  const t = useTranslations("feed");
  const isTouch = useMediaQuery("(pointer: coarse)");
  const reducedMotion = useMediaQuery("(prefers-reduced-motion: reduce)");
  const [refreshing, setRefreshing] = useState(false);

  const contentRef = useRef<HTMLDivElement | null>(null);
  const spinnerRef = useRef<HTMLDivElement | null>(null);
  const refreshingRef = useRef(false);

  // Keep latest values without re-binding the touch listeners.
  const onRefreshRef = useRef(onRefresh);
  const reducedMotionRef = useRef(reducedMotion);
  useEffect(() => {
    onRefreshRef.current = onRefresh;
    reducedMotionRef.current = reducedMotion;
  });

  /** Apply the pull offset directly to the DOM (no re-render per frame). */
  const setVisual = useCallback((pull: number, animate: boolean) => {
    const reduce = reducedMotionRef.current;
    const content = contentRef.current;
    if (content) {
      content.style.transition =
        animate && !reduce
          ? "transform 0.3s cubic-bezier(0.2, 1.4, 0.4, 1)"
          : "";
      content.style.transform = pull > 0 ? `translateY(${pull}px)` : "";
    }
    const spinner = spinnerRef.current;
    if (spinner) {
      const progress = Math.min(1, pull / THRESHOLD);
      spinner.style.opacity = String(progress);
      spinner.style.transform = reduce
        ? ""
        : `rotate(${Math.round(progress * 270)}deg)`;
    }
  }, []);

  const runRefresh = useCallback(async () => {
    if (refreshingRef.current) return;
    refreshingRef.current = true;
    setRefreshing(true);
    try {
      await onRefreshRef.current();
    } finally {
      refreshingRef.current = false;
      setRefreshing(false);
      setVisual(0, true);
    }
  }, [setVisual]);

  useEffect(() => {
    const node = contentRef.current?.parentElement;
    if (!node || !isTouch) return;

    const gesture = { startY: 0, pull: 0, pulling: false, armed: false };

    const onTouchStart = (event: TouchEvent) => {
      if (!atTop() || refreshingRef.current) {
        gesture.pulling = false;
        return;
      }
      gesture.startY = event.touches[0].clientY;
      gesture.pull = 0;
      gesture.armed = false;
      gesture.pulling = true;
    };

    const onTouchMove = (event: TouchEvent) => {
      if (!gesture.pulling || refreshingRef.current) return;
      const dy = event.touches[0].clientY - gesture.startY;
      if (dy <= 0 || !atTop()) {
        // Finger moved back up or the page scrolled — hand back to the
        // browser and reset any partial pull.
        if (gesture.pull > 0) {
          gesture.pull = 0;
          gesture.armed = false;
          setVisual(0, false);
        }
        return;
      }
      // We own this gesture: block native scroll/overscroll.
      event.preventDefault();
      // Rubber-band damping: approaches MAX_PULL asymptotically.
      const pull = MAX_PULL * (1 - Math.exp(-dy / MAX_PULL));
      gesture.pull = pull;
      if (!gesture.armed && pull >= THRESHOLD) {
        gesture.armed = true;
        // Haptic tick where supported.
        if ("vibrate" in navigator) navigator.vibrate?.(10);
      } else if (gesture.armed && pull < THRESHOLD) {
        gesture.armed = false;
      }
      setVisual(pull, false);
    };

    const onTouchEnd = () => {
      if (!gesture.pulling) return;
      gesture.pulling = false;
      if (gesture.armed && gesture.pull >= THRESHOLD) {
        // Snap to the resting position and refresh.
        setVisual(THRESHOLD, true);
        void runRefresh();
      } else if (gesture.pull > 0) {
        setVisual(0, true);
      }
      gesture.pull = 0;
      gesture.armed = false;
    };

    node.addEventListener("touchstart", onTouchStart, { passive: true });
    // passive: false so preventDefault can suppress native scroll while
    // the pull gesture is active.
    node.addEventListener("touchmove", onTouchMove, { passive: false });
    node.addEventListener("touchend", onTouchEnd);
    node.addEventListener("touchcancel", onTouchEnd);
    return () => {
      node.removeEventListener("touchstart", onTouchStart);
      node.removeEventListener("touchmove", onTouchMove);
      node.removeEventListener("touchend", onTouchEnd);
      node.removeEventListener("touchcancel", onTouchEnd);
      setVisual(0, false);
    };
  }, [isTouch, runRefresh, setVisual]);

  return (
    <div className={cn("relative overscroll-y-contain", className)}>
      {isTouch ? (
        <div
          className="pointer-events-none absolute inset-x-0 top-2 z-0 flex justify-center"
          aria-hidden
        >
          <div
            ref={spinnerRef}
            className={cn(
              "flex size-9 items-center justify-center rounded-full border bg-background shadow-sm",
              refreshing && !reducedMotion && "animate-spin",
            )}
            style={{ opacity: refreshing ? 1 : 0 }}
          >
            <RefreshCw className="size-4 text-muted-foreground" />
          </div>
        </div>
      ) : (
        <div className="mb-1 flex justify-end">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
            disabled={refreshing}
            onClick={() => void runRefresh()}
          >
            <RefreshCw
              className={cn(
                "size-3.5",
                refreshing && !reducedMotion && "animate-spin",
              )}
              aria-hidden
            />
            {refreshing ? t("refreshing") : t("refresh")}
          </Button>
        </div>
      )}
      <div ref={contentRef} className="relative z-10 will-change-transform">
        {children}
      </div>
      <span aria-live="polite" className="sr-only">
        {refreshing ? t("refreshingFeed") : ""}
      </span>
    </div>
  );
}
