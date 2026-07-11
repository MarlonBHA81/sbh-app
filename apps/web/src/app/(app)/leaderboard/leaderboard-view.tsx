"use client";

import { Trophy } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type {
  LeaderboardPeriod,
  LeaderboardResponse,
  LeaderboardRow,
} from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

const MEDALS: Record<number, string> = { 1: "🥇", 2: "🥈", 3: "🥉" };

function formatXp(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

function PositionMarker({ position }: { position: number }) {
  const medal = MEDALS[position];
  if (medal) {
    return (
      <span
        className="flex w-8 shrink-0 justify-center text-xl"
        aria-label={`Position ${position}`}
      >
        {medal}
      </span>
    );
  }
  return (
    <span className="flex w-8 shrink-0 justify-center text-sm font-semibold text-muted-foreground tabular-nums">
      {position}
    </span>
  );
}

function Row({ row, isViewer }: { row: LeaderboardRow; isViewer: boolean }) {
  const { profile, position, xp } = row;
  return (
    <Link
      href={`/${profile.handle}`}
      className={cn(
        "flex items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors hover:bg-accent/40",
        isViewer && "border-primary/40 bg-accent/40",
        position <= 3 && "border-amber-500/30",
      )}
    >
      <PositionMarker position={position} />
      <ProfileAvatar profile={profile} className="size-9" />
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="flex items-center gap-1.5 truncate text-sm font-medium">
          {profile.name}
          {isViewer ? (
            <span className="text-xs font-normal text-muted-foreground">
              (You)
            </span>
          ) : null}
        </span>
        <span className="truncate text-xs text-muted-foreground">
          @{profile.handle}
        </span>
      </span>
      <span className="shrink-0 text-sm font-semibold tabular-nums">
        {formatXp(xp)} XP
      </span>
    </Link>
  );
}

function RowSkeleton() {
  return (
    <div className="flex items-center gap-3 rounded-xl border px-3 py-2.5">
      <Skeleton className="h-5 w-8" />
      <Skeleton className="size-9 rounded-full" />
      <div className="flex flex-1 flex-col gap-1.5">
        <Skeleton className="h-3.5 w-32" />
        <Skeleton className="h-3 w-20" />
      </div>
      <Skeleton className="h-4 w-12" />
    </div>
  );
}

function Board({ period }: { period: LeaderboardPeriod }) {
  const viewerUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const [retry, setRetry] = useState(0);
  const key = `${period}:${retry}`;
  const [state, setState] = useState<{
    key: string;
    phase: "loading" | "loaded" | "error";
    data: LeaderboardResponse | null;
  }>({ key, phase: "loading", data: null });

  // Reset to a loading state while rendering when the period/retry changes,
  // avoiding a synchronous setState inside the effect.
  if (state.key !== key) {
    setState({ key, phase: "loading", data: null });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<LeaderboardResponse>(
        `/api/v1/gamification/leaderboard?period=${period}`,
      )
      .then((res) => {
        if (!cancelled) setState({ key, phase: "loaded", data: res });
      })
      .catch(() => {
        if (!cancelled) setState({ key, phase: "error", data: null });
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [period, retry]);

  if (state.phase === "loading") {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: 8 }).map((_, i) => (
          <RowSkeleton key={i} />
        ))}
      </div>
    );
  }

  if (state.phase === "error" || !state.data) {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        Couldn&apos;t load the leaderboard.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => setRetry((c) => c + 1)}
        >
          Try again
        </button>
      </p>
    );
  }

  const { data, viewer } = state.data;

  if (data.length === 0) {
    return (
      <EmptyState
        icon={Trophy}
        title="No rankings yet"
        description="Earn XP by posting and engaging — the leaderboard fills up as the community gets active."
      />
    );
  }

  const viewerInList =
    viewerUlid != null && data.some((row) => row.profile.ulid === viewerUlid);
  const showFooter = viewer != null && !viewerInList;

  return (
    <div className="flex flex-col gap-2">
      {data.map((row) => (
        <Row
          key={row.profile.ulid}
          row={row}
          isViewer={viewerUlid != null && row.profile.ulid === viewerUlid}
        />
      ))}

      {showFooter ? (
        <div className="sticky bottom-4 mt-2">
          <div className="flex items-center gap-3 rounded-xl border border-primary/40 bg-accent px-4 py-3 shadow-md">
            <span className="text-sm font-semibold text-muted-foreground tabular-nums">
              #{viewer.position}
            </span>
            <span className="flex-1 text-sm font-medium">Your position</span>
            <span className="text-sm font-semibold tabular-nums">
              {formatXp(viewer.xp)} XP
            </span>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export function LeaderboardView() {
  const [period, setPeriod] = useState<LeaderboardPeriod>("weekly");

  return (
    <Tabs
      value={period}
      onValueChange={(value) => setPeriod(value as LeaderboardPeriod)}
    >
      <TabsList className="w-full">
        <TabsTrigger value="weekly" className="h-10 flex-1">
          This week
        </TabsTrigger>
        <TabsTrigger value="all" className="h-10 flex-1">
          All time
        </TabsTrigger>
      </TabsList>
      <TabsContent value="weekly" className="pt-2">
        <Board period="weekly" />
      </TabsContent>
      <TabsContent value="all" className="pt-2">
        <Board period="all" />
      </TabsContent>
    </Tabs>
  );
}
