"use client";

import { Check, ChevronDown, Sparkles } from "lucide-react";
import { useEffect, useState } from "react";

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { XpAction, XpSummary } from "@/lib/api/types";
import { cn } from "@/lib/utils";

function formatXp(n: number): string {
  return Intl.NumberFormat("en").format(n);
}

function ActionRow({ action }: { action: XpAction }) {
  const capped =
    action.daily_cap > 0 && action.times_today >= action.daily_cap;

  return (
    <li className="flex items-center gap-3 py-2">
      <span
        className={cn(
          "flex size-6 shrink-0 items-center justify-center rounded-full",
          capped
            ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
            : "bg-muted text-muted-foreground",
        )}
      >
        {capped ? (
          <Check className="size-3.5" aria-hidden />
        ) : (
          <Sparkles className="size-3.5" aria-hidden />
        )}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium">{action.label}</span>
        {action.daily_cap > 0 ? (
          <span className="block text-xs text-muted-foreground tabular-nums">
            {action.times_today} of {action.daily_cap} today
            {capped ? " · done" : ""}
          </span>
        ) : null}
      </span>
      <span className="shrink-0 text-sm font-semibold text-primary tabular-nums">
        +{action.points}
      </span>
    </li>
  );
}

/**
 * "Your progress" card for the viewer's own profile: current rank, progress
 * toward the next rank, and a collapsible list of ways to earn XP with daily
 * cap state. Fetches /api/v1/me/xp; renders nothing if it can't load.
 */
export function XpProgressCard() {
  const [summary, setSummary] = useState<XpSummary | null>(null);
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">(
    "loading",
  );

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: XpSummary }>("/api/v1/me/xp")
      .then((res) => {
        if (!cancelled) {
          setSummary(res.data);
          setPhase("loaded");
        }
      })
      .catch(() => {
        if (!cancelled) setPhase("error");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (phase === "error") return null;

  if (phase === "loading" || !summary) {
    return (
      <Card>
        <CardContent className="flex flex-col gap-3">
          <Skeleton className="h-5 w-32" />
          <Skeleton className="h-2 w-full rounded-full" />
          <Skeleton className="h-4 w-40" />
        </CardContent>
      </Card>
    );
  }

  const { rank, next_rank, xp_total, today } = summary;

  return (
    <Card>
      <CardContent className="flex flex-col gap-4">
        <div className="flex items-center gap-3">
          <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-2xl">
            <span aria-hidden>{rank.icon}</span>
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xs font-medium text-muted-foreground">
              Your progress
            </p>
            <p className="text-sm font-semibold">{rank.name}</p>
          </div>
          <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
            {formatXp(xp_total)} XP
          </span>
        </div>

        {next_rank ? (
          <div className="flex flex-col gap-1.5">
            <Progress value={next_rank.progress_pct} />
            <p className="text-xs text-muted-foreground tabular-nums">
              {formatXp(xp_total)} / {formatXp(next_rank.min_xp)} XP to{" "}
              {next_rank.name}
            </p>
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">
            You&apos;ve reached the top rank. 🎉
          </p>
        )}

        {today.length > 0 ? (
          <Collapsible>
            <CollapsibleTrigger className="group flex w-full items-center justify-between rounded-md py-1 text-sm font-medium text-foreground">
              How to earn XP
              <ChevronDown
                className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                aria-hidden
              />
            </CollapsibleTrigger>
            <CollapsibleContent>
              <ul className="divide-y">
                {today.map((action) => (
                  <ActionRow key={action.action_key} action={action} />
                ))}
              </ul>
            </CollapsibleContent>
          </Collapsible>
        ) : null}
      </CardContent>
    </Card>
  );
}
