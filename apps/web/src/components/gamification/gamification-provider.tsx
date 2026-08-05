"use client";

import { Sparkles } from "lucide-react";
import { useRef } from "react";
import { toast } from "sonner";

import { RankCelebrationDialog } from "@/components/gamification/rank-celebration";
import type { XpAwardedEvent } from "@/lib/api/types";
import { useEchoPrivateEvents } from "@/lib/echo";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useGamificationStore } from "@/lib/stores/gamification-store";

/** Awards arriving within this window collapse into a single toast. */
const BURST_WINDOW_MS = 3000;

function asXpAwarded(payload: unknown): XpAwardedEvent | null {
  if (!payload || typeof payload !== "object") return null;
  const p = payload as Record<string, unknown>;
  if (typeof p.points !== "number") return null;
  return {
    points: p.points,
    action_key: typeof p.action_key === "string" ? p.action_key : "",
    xp_total: typeof p.xp_total === "number" ? p.xp_total : 0,
    label: typeof p.label === "string" ? p.label : "XP earned",
  };
}

function XpToast({
  points,
  detail,
}: {
  points: number;
  detail: string;
}) {
  return (
    <div className="flex items-center gap-2.5 rounded-md border bg-popover px-3 py-2.5 text-sm text-popover-foreground shadow-md">
      <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gold/15 text-gold-text">
        <Sparkles className="size-4" aria-hidden />
      </span>
      <span className="min-w-0">
        <span className="font-semibold tabular-nums">+{points} XP</span>
        <span className="text-muted-foreground"> · {detail}</span>
      </span>
    </div>
  );
}

interface Burst {
  id: string;
  sum: number;
  count: number;
  lastLabel: string;
  timer: ReturnType<typeof setTimeout>;
}

/**
 * App-wide gamification driver. Subscribes to `.XpAwarded` on the active
 * profile's private channel and raises XP toasts, collapsing rapid bursts into
 * one running-sum toast. Also renders the rank-unlock celebration dialog, whose
 * state is fed by the notifications pipeline. Renders nothing but the dialog.
 *
 * Realtime is enhancement-only: without Echo, nothing here fires and the app is
 * unaffected.
 */
export function GamificationProvider() {
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const pendingRank = useGamificationStore((s) => s.pendingRank);
  const dismissRank = useGamificationStore((s) => s.dismissRank);
  const burstRef = useRef<Burst | null>(null);

  useEchoPrivateEvents(activeUlid ? `profile.${activeUlid}` : null, {
    ".XpAwarded": (payload) => {
      const event = asXpAwarded(payload);
      if (!event) return;

      const active = burstRef.current;
      if (active) {
        // Extend the in-flight burst: bump the running sum and re-render the
        // same toast id, then reset the collapse timer.
        clearTimeout(active.timer);
        active.sum += event.points;
        active.count += 1;
        active.lastLabel = event.label;
        active.timer = setTimeout(() => {
          burstRef.current = null;
        }, BURST_WINDOW_MS);

        const detail =
          active.count > 1 ? `${active.count} actions` : active.lastLabel;
        toast.custom(() => <XpToast points={active.sum} detail={detail} />, {
          id: active.id,
          duration: BURST_WINDOW_MS + 1200,
        });
        return;
      }

      // Start a new burst.
      const id = `xp-${Date.now()}`;
      const burst: Burst = {
        id,
        sum: event.points,
        count: 1,
        lastLabel: event.label,
        timer: setTimeout(() => {
          burstRef.current = null;
        }, BURST_WINDOW_MS),
      };
      burstRef.current = burst;
      toast.custom(
        () => <XpToast points={burst.sum} detail={burst.lastLabel} />,
        { id, duration: BURST_WINDOW_MS + 1200 },
      );
    },
  });

  return (
    <RankCelebrationDialog rank={pendingRank} onDismiss={dismissRank} />
  );
}
