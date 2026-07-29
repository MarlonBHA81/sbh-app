"use client";

import { useMemo } from "react";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import type { RankSummary } from "@/lib/api/types";

const CONFETTI_COLORS = [
  "oklch(0.828 0.189 84.429)", // amber
  "oklch(0.696 0.17 162.48)", // green
  "oklch(0.627 0.265 303.9)", // violet
  "oklch(0.645 0.246 16.439)", // pink
  "oklch(0.6 0.118 184.704)", // teal
];

/** Deterministic-ish confetti pieces built once per open. */
function ConfettiField() {
  const pieces = useMemo(
    () =>
      Array.from({ length: 28 }, (_, i) => ({
        left: `${(i * 37) % 100}%`,
        color: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
        delay: `${(i % 7) * 0.12}s`,
        duration: `${2 + ((i * 13) % 12) / 10}s`,
        drift: `${((i % 5) - 2) * 24}px`,
      })),
    [],
  );

  return (
    <div
      aria-hidden
      className="pointer-events-none absolute inset-0 overflow-hidden"
    >
      {pieces.map((p, i) => (
        <span
          key={i}
          className="sbh-confetti"
          style={
            {
              left: p.left,
              backgroundColor: p.color,
              "--sbh-confetti-delay": p.delay,
              "--sbh-confetti-dur": p.duration,
              "--sbh-confetti-x": p.drift,
            } as React.CSSProperties
          }
        />
      ))}
    </div>
  );
}

export function RankCelebrationDialog({
  rank,
  onDismiss,
}: {
  rank: RankSummary | null;
  onDismiss: () => void;
}) {
  const open = rank != null;

  return (
    <Dialog open={open} onOpenChange={(next) => (!next ? onDismiss() : undefined)}>
      <DialogContent
        showCloseButton={false}
        className="overflow-hidden text-center"
      >
        {rank ? <ConfettiField /> : null}
        <div className="relative flex flex-col items-center gap-4 py-2">
          <span className="sbh-rank-icon flex size-24 items-center justify-center rounded-full bg-gold/15 text-5xl">
            <span aria-hidden>{rank?.icon ?? "🏆"}</span>
          </span>
          <div className="flex flex-col gap-1">
            <DialogTitle className="text-2xl">
              You reached {rank?.name}!
            </DialogTitle>
            <DialogDescription>
              Keep posting and engaging to climb even higher.
            </DialogDescription>
          </div>
          <Button className="mt-1 h-11 w-full sm:w-40" onClick={onDismiss}>
            Continue
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
