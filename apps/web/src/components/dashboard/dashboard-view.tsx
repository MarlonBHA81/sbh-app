"use client";

import { Flame, HandHeart, Sparkles, Target, Trophy } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { GoalsPanel } from "@/components/dashboard/goals-panel";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { DashboardData, Goal } from "@/lib/api/types";

function formatNumber(n: number): string {
  return Intl.NumberFormat("en").format(n);
}

function StatTile({
  icon: Icon,
  label,
  value,
  tint,
}: {
  icon: typeof Target;
  label: string;
  value: string;
  tint: string;
}) {
  return (
    <div className="flex flex-col gap-2 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <span
        className={`flex size-8 items-center justify-center rounded-full ${tint}`}
      >
        <Icon className="size-4" aria-hidden />
      </span>
      <span className="font-heading text-2xl font-semibold text-text-primary tabular-nums">
        {value}
      </span>
      <span className="text-xs text-text-secondary">{label}</span>
    </div>
  );
}

/**
 * Business dashboard (V3 · PROGRESS) — a member's honest "see your growth"
 * view: real, self-owned signals (goals, streak, helpful count, posts, XP).
 * No invented numbers, no fear framing; a member at the start simply sees zeros
 * with an encouraging next step, never a fabricated figure.
 */
export function DashboardView() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">("loading");

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: DashboardData }>("/api/v1/me/dashboard")
      .then((res) => {
        if (cancelled) return;
        setData(res.data);
        setPhase("loaded");
      })
      .catch(() => {
        if (!cancelled) setPhase("error");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  function onGoalsChange(goals: Goal[]) {
    setData((prev) =>
      prev
        ? {
            ...prev,
            goals,
            stats: {
              ...prev.stats,
              goals_total: goals.length,
              goals_completed: goals.filter((g) => g.is_done).length,
            },
          }
        : prev,
    );
  }

  return (
    <div className="flex flex-col gap-5">
      <ScreenHeader title="Your dashboard" />
      <p className="text-sm text-text-secondary">
        Your progress, in your own words — the goals you set and the growth
        you&apos;ve earned.
      </p>

      {phase === "error" ? (
        <p className="rounded-(--radius-card) border border-warmgray bg-card p-4 text-sm text-text-secondary">
          We couldn&apos;t load your dashboard just now. Pull to refresh or try
          again shortly.
        </p>
      ) : phase === "loading" || !data ? (
        <div className="grid grid-cols-2 gap-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-28 w-full rounded-(--radius-card)" />
          ))}
        </div>
      ) : (
        <>
          <section className="grid grid-cols-2 gap-3">
            <StatTile
              icon={Target}
              label={
                data.stats.goals_total > 0
                  ? `${data.stats.goals_completed} of ${data.stats.goals_total} goals reached`
                  : "Goals reached"
              }
              value={formatNumber(data.stats.goals_completed)}
              tint="bg-teal/12 text-teal-text"
            />
            <StatTile
              icon={Flame}
              label="Day streak"
              value={formatNumber(data.stats.streak_days)}
              tint="bg-plum/12 text-plum"
            />
            <StatTile
              icon={HandHeart}
              label="Times you helped"
              value={formatNumber(data.stats.helpful_count)}
              tint="bg-sage/15 text-sage-ink"
            />
            <StatTile
              icon={Sparkles}
              label="Posts shared"
              value={formatNumber(data.stats.posts_count)}
              tint="bg-slate/12 text-slate"
            />
          </section>

          <div className="flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
            <span className="flex size-9 items-center justify-center rounded-full bg-teal/12 text-teal-text">
              <Trophy className="size-4" aria-hidden />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-xs font-medium text-text-secondary">
                Experience earned
              </p>
              <p className="font-heading text-lg font-semibold text-text-primary tabular-nums">
                {formatNumber(data.stats.xp_total)} XP
              </p>
            </div>
          </div>

          <GoalsPanel
            goals={data.goals}
            onGoalsChange={onGoalsChange}
            onError={() => toast.error("Couldn't save — try again.")}
          />
        </>
      )}
    </div>
  );
}
