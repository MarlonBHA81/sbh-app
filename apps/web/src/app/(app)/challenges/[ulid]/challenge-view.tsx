"use client";

import { Swords } from "lucide-react";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { ChallengeCard } from "@/components/gamification/challenges-section";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { ChallengeDetail } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

import { Row, RowSkeleton } from "@/app/(app)/leaderboard/leaderboard-view";

function formatXp(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

export function ChallengeView({ ulid }: { ulid: string }) {
  const viewerUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const [state, setState] = useState<{
    key: string;
    phase: "loading" | "loaded" | "error";
    challenge: ChallengeDetail | null;
  }>({ key: ulid, phase: "loading", challenge: null });

  if (state.key !== ulid) {
    setState({ key: ulid, phase: "loading", challenge: null });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: ChallengeDetail }>(`/api/v1/challenges/${ulid}`)
      .then((res) => {
        if (!cancelled) {
          setState({ key: ulid, phase: "loaded", challenge: res.data });
        }
      })
      .catch(() => {
        if (!cancelled) setState({ key: ulid, phase: "error", challenge: null });
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  const { phase, challenge } = state;

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Challenge" />

      {phase === "loading" ? (
        <>
          <Skeleton className="h-28 w-full rounded-(--radius-card)" />
          {Array.from({ length: 6 }).map((_, i) => (
            <RowSkeleton key={i} />
          ))}
        </>
      ) : null}

      {phase === "error" || (phase === "loaded" && !challenge) ? (
        <EmptyState
          icon={Swords}
          title="Challenge not found"
          description="It may have been removed or hasn't started yet."
        />
      ) : null}

      {challenge ? (
        <>
          <ChallengeCard
            challenge={challenge}
            onChanged={(next) =>
              setState((prev) =>
                prev.challenge
                  ? { ...prev, challenge: { ...prev.challenge, ...next } }
                  : prev,
              )
            }
          />

          {challenge.entries.length === 0 ? (
            <EmptyState
              icon={Swords}
              title="No entries yet"
              description="Join the challenge and start earning XP — every post, comment and like counts."
            />
          ) : (
            <div className="flex flex-col gap-2">
              {challenge.entries.map((row) => (
                <Row
                  key={row.profile.ulid}
                  row={row}
                  isViewer={viewerUlid != null && row.profile.ulid === viewerUlid}
                />
              ))}
              {challenge.me &&
              !challenge.entries.some((r) => r.profile.ulid === viewerUlid) ? (
                <div className="sticky bottom-4 mt-2">
                  <div className="flex items-center gap-3 rounded-xl border border-primary/40 bg-accent px-4 py-3 shadow-md">
                    <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                      #{challenge.me.position}
                    </span>
                    <span className="flex-1 text-sm font-medium">
                      Your position
                    </span>
                    <span className="text-sm font-semibold tabular-nums">
                      {formatXp(challenge.me.xp)} XP
                    </span>
                  </div>
                </div>
              ) : null}
            </div>
          )}
        </>
      ) : null}
    </div>
  );
}
