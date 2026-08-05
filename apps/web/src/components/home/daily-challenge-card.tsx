"use client";

import { Check, Flame, Target } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { HomeCard, type HomeCardTier } from "@/components/home/home-card";
import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { DailyChallenge } from "@/lib/api/types";

/**
 * "Today's Challenge" card (V1 · PROGRESS): one small daily action. Completing
 * it awards XP and advances the streak — a preview of the Daily Home dashboard
 * and the small-actions-create-momentum idea.
 */
export function DailyChallengeCard({ tier }: { tier?: HomeCardTier }) {
  const [daily, setDaily] = useState<DailyChallenge | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: DailyChallenge }>("/api/v1/me/daily")
      .then((res) => {
        if (!cancelled) setDaily(res.data);
      })
      .catch(() => {
        // Non-fatal: leave the card hidden.
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (!daily?.action) return null;

  const { action, completed, streak } = daily;

  async function complete() {
    if (busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: DailyChallenge }>(
        "/api/v1/me/daily/complete",
      );
      setDaily((prev) => (prev ? { ...prev, completed: true, streak: res.data.streak } : prev));
      const days = res.data.streak.current_days;
      toast.success(
        days > 1 ? `Done! ${days}-day streak 🔥` : "Done! Streak started 🔥",
      );
    } catch {
      toast.error("Couldn't save — try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <HomeCard
      tier={tier}
      tone="teal"
      icon={Target}
      title="Today's challenge"
      aside={
        streak.current_days > 0 ? (
          <span className="flex items-center gap-1 rounded-full bg-teal/12 px-2 py-0.5 text-[11px] font-medium text-teal-text">
            <Flame className="size-3" aria-hidden />
            {streak.current_days}-day
          </span>
        ) : null
      }
    >
      <div className="flex flex-col gap-0.5">
        <p className="text-[15px] font-medium text-text-primary">{action.title}</p>
        {action.description ? (
          <p className="text-[13px] text-text-secondary">{action.description}</p>
        ) : null}
      </div>

      {completed ? (
        <div className="flex items-center gap-2 rounded-xl bg-teal/10 px-3 py-2 text-[13px] font-medium text-teal-text">
          <Check className="size-4" aria-hidden />
          Done for today — nice work.
        </div>
      ) : (
        <Button
          type="button"
          className="h-11"
          disabled={busy}
          onClick={() => void complete()}
        >
          {busy ? "Saving…" : "Mark as done"}
        </Button>
      )}
    </HomeCard>
  );
}
