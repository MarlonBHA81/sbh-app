"use client";

import { CalendarRange, Users } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { SectionHeader } from "@/components/home/section-header";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Challenge } from "@/lib/api/types";
import { cn } from "@/lib/utils";

function dateRange(challenge: Challenge): string {
  const opts: Intl.DateTimeFormatOptions = { day: "numeric", month: "short" };
  const start = new Date(challenge.starts_at).toLocaleDateString(undefined, opts);
  const end = new Date(challenge.ends_at).toLocaleDateString(undefined, opts);
  return `${start} – ${end}`;
}

const STATUS_META: Record<Challenge["status"], { label: string; className: string }> = {
  active: { label: "Live", className: "bg-teal text-white" },
  upcoming: { label: "Upcoming", className: "bg-warmgray text-text-secondary" },
  ended: { label: "Ended", className: "bg-warmgray text-text-secondary" },
};

/** One challenge card with a join toggle (used on the Leaderboard screen). */
export function ChallengeCard({
  challenge,
  onChanged,
}: {
  challenge: Challenge;
  onChanged: (next: Challenge) => void;
}) {
  const [busy, setBusy] = useState(false);
  const status = STATUS_META[challenge.status];

  async function toggleJoin(event: React.MouseEvent) {
    event.preventDefault();
    if (busy || challenge.status === "ended") return;
    setBusy(true);
    const joining = !challenge.joined;
    try {
      if (joining) {
        await api.post(`/api/v1/challenges/${challenge.ulid}/join`);
        toast.success("You're in — XP you earn now counts!");
      } else {
        await api.del(`/api/v1/challenges/${challenge.ulid}/join`);
      }
      onChanged({
        ...challenge,
        joined: joining,
        participants_count: Math.max(
          0,
          challenge.participants_count + (joining ? 1 : -1),
        ),
      });
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Link
      href={`/challenges/${challenge.ulid}`}
      className="flex flex-col gap-2.5 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card transition-colors hover:border-warmgray-tint"
    >
      <div className="flex items-start justify-between gap-3">
        <span className="font-heading text-sm font-semibold">
          {challenge.title}
        </span>
        <span
          className={cn(
            "shrink-0 rounded-full px-2.5 py-0.5 font-heading text-[11px] font-medium",
            status.className,
          )}
        >
          {status.label}
        </span>
      </div>
      {challenge.description ? (
        <p className="line-clamp-2 text-sm text-text-secondary">
          {challenge.description}
        </p>
      ) : null}
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-text-secondary">
        <span className="flex items-center gap-1.5">
          <CalendarRange className="size-3.5" aria-hidden />
          {dateRange(challenge)}
        </span>
        <span className="flex items-center gap-1.5">
          <Users className="size-3.5" aria-hidden />
          {challenge.participants_count}
        </span>
        {challenge.status !== "ended" ? (
          <Button
            type="button"
            size="sm"
            variant={challenge.joined ? "outline" : "default"}
            className="ms-auto h-9"
            disabled={busy}
            onClick={(event) => void toggleJoin(event)}
          >
            {challenge.joined ? "Joined ✓" : "Join challenge"}
          </Button>
        ) : null}
      </div>
    </Link>
  );
}

/** Challenges list for the Leaderboard screen. Hidden when none exist. */
export function ChallengesSection() {
  const [challenges, setChallenges] = useState<Challenge[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Challenge[] }>("/api/v1/challenges")
      .then((res) => {
        if (!cancelled) setChallenges(res.data);
      })
      .catch(() => {
        if (!cancelled) setChallenges([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (challenges === null) {
    return <Skeleton className="h-28 w-full rounded-(--radius-card)" />;
  }

  if (challenges.length === 0) return null;

  return (
    <section className="flex flex-col gap-3">
      <SectionHeader title="Challenges" />
      <div className="flex flex-col gap-3">
        {challenges.map((challenge) => (
          <ChallengeCard
            key={challenge.ulid}
            challenge={challenge}
            onChanged={(next) =>
              setChallenges((prev) =>
                (prev ?? []).map((c) => (c.ulid === next.ulid ? next : c)),
              )
            }
          />
        ))}
      </div>
    </section>
  );
}
