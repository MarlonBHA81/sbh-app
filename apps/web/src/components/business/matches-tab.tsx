"use client";

import {
  ArrowRight,
  BadgeCheck,
  Briefcase,
  MapPin,
  MessageCircle,
  Sparkles,
} from "lucide-react";
import Link from "next/link";
import {
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from "react";

import { CategoryChip } from "@/components/business/category-chip";
import { NeedsManager } from "@/components/business/needs-manager";
import { EmptyState } from "@/components/empty-state";
import { ProfileAvatar } from "@/components/profile-avatar";
import { PullToRefresh } from "@/components/posts/pull-to-refresh";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useStartDm } from "@/hooks/use-start-dm";
import * as api from "@/lib/api/client";
import type { BusinessMatch, BusinessMatchReason } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

function scoreBadge(score: number): { label: string; variant: "default" | "secondary" | "outline" } {
  if (score >= 6) return { label: "Strong match", variant: "default" };
  if (score >= 4) return { label: "Good match", variant: "secondary" };
  return { label: "Match", variant: "outline" };
}

function ReasonRow({ reason }: { reason: BusinessMatchReason }) {
  const iOffer = reason.my_need.kind === "offering";
  const mine = reason.my_need.category.name;
  const theirs = reason.their_need.category.name;
  const line = iOffer
    ? `You offer ${mine} → they're seeking ${theirs}`
    : `You're seeking ${mine} → they offer ${theirs}`;

  return (
    <div className="flex flex-col gap-1 rounded-lg bg-muted/50 p-2.5">
      <span className="flex items-center gap-1.5 text-xs font-medium">
        <ArrowRight className="size-3.5 shrink-0 text-primary" aria-hidden />
        <span>{line}</span>
      </span>
      {reason.their_need.description ? (
        <span className="line-clamp-2 pl-5 text-xs text-muted-foreground">
          “{reason.their_need.description}”
        </span>
      ) : null}
    </div>
  );
}

function MatchCard({ match }: { match: BusinessMatch }) {
  const { profile } = match;
  const badge = scoreBadge(match.score);
  const { startDm, busyUlid } = useStartDm();

  return (
    <div className="flex flex-col gap-3 rounded-xl border p-4">
      <div className="flex items-start gap-3">
        <Link href={`/${profile.handle}`} className="shrink-0">
          <ProfileAvatar profile={profile} className="size-12" />
        </Link>
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <Link
            href={`/${profile.handle}`}
            className="flex items-center gap-1 font-medium leading-tight hover:underline"
          >
            <span className="truncate">{profile.name}</span>
            {profile.is_verified ? (
              <BadgeCheck
                className="size-4 shrink-0 text-teal-text"
                aria-label="Verified"
              />
            ) : null}
          </Link>
          <span className="truncate text-xs text-muted-foreground">
            @{profile.handle}
          </span>
          <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
            {profile.business_category ? (
              <CategoryChip category={profile.business_category} />
            ) : null}
            {profile.location ? (
              <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <MapPin className="size-3 shrink-0" aria-hidden />
                <span className="truncate">{profile.location}</span>
              </span>
            ) : null}
          </span>
        </div>
        <Badge variant={badge.variant} className="shrink-0 gap-1">
          <Sparkles className="size-3" aria-hidden />
          {badge.label}
        </Badge>
      </div>

      {match.matches.length > 0 ? (
        <div className="flex flex-col gap-1.5">
          {match.matches.map((reason, i) => (
            <ReasonRow key={i} reason={reason} />
          ))}
        </div>
      ) : null}

      <div className="flex gap-2">
        <Button asChild variant="outline" size="sm" className="h-9 flex-1">
          <Link href={`/${profile.handle}`}>View profile</Link>
        </Button>
        <Button
          type="button"
          size="sm"
          className="h-9 flex-1 gap-1.5"
          disabled={busyUlid === profile.ulid}
          onClick={() => void startDm(profile)}
        >
          <MessageCircle className="size-4" aria-hidden />
          Message
        </Button>
      </div>
    </div>
  );
}

export interface MatchResultsHandle {
  refresh: () => Promise<void>;
}

function MatchResults({
  reloadKey,
  ref,
}: {
  reloadKey: number;
  ref?: React.Ref<MatchResultsHandle>;
}) {
  const [attempt, setAttempt] = useState(0);
  const key = `${reloadKey}#${attempt}`;
  const [state, setState] = useState<{
    key: string;
    matches: BusinessMatch[];
    phase: "loading" | "loaded" | "error";
  }>({ key, matches: [], phase: "loading" });

  // Reset to loading while rendering when the load identity changes.
  if (state.key !== key) {
    setState({ key, matches: [], phase: "loading" });
  }
  const { matches, phase } = state;

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: BusinessMatch[] }>("/api/v1/business/matches")
      .then((res) => {
        if (!cancelled) {
          setState({ key, matches: res.data, phase: "loaded" });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key, matches: [], phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [key]);

  const refresh = useCallback(async () => {
    try {
      const res = await api.get<{ data: BusinessMatch[] }>(
        "/api/v1/business/matches",
      );
      setState((prev) => ({ ...prev, matches: res.data, phase: "loaded" }));
    } catch {
      // Keep current results on failure.
    }
  }, []);

  useImperativeHandle(ref, () => ({ refresh }), [refresh]);

  if (phase === "loading") {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="flex flex-col gap-3 rounded-xl border p-4">
            <div className="flex items-center gap-3">
              <Skeleton className="size-12 rounded-full" />
              <div className="flex flex-1 flex-col gap-1.5">
                <Skeleton className="h-4 w-40" />
                <Skeleton className="h-3 w-24" />
              </div>
              <Skeleton className="h-6 w-24 rounded-full" />
            </div>
            <Skeleton className="h-12 w-full rounded-lg" />
          </div>
        ))}
      </div>
    );
  }

  if (phase === "error") {
    return (
      <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
        Couldn&apos;t load matches.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => setAttempt((n) => n + 1)}
        >
          Try again
        </button>
      </div>
    );
  }

  if (matches.length === 0) {
    return (
      <EmptyState
        icon={Sparkles}
        title="No matches yet"
        description="Add more needs or check back soon — we'll surface businesses that fit what you offer and seek."
      />
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {matches.map((match) => (
        <MatchCard key={match.profile.ulid} match={match} />
      ))}
    </div>
  );
}

/** Shown when a personal profile is active — matchmaking is business-only. */
function PersonalGate() {
  return (
    <EmptyState
      icon={Briefcase}
      title="Matchmaking is for business profiles"
      description="Switch to your business profile to list what you offer and get matched with other businesses."
    >
      <AccountSwitcher>
        <Button className="mt-2 h-11">Switch profile</Button>
      </AccountSwitcher>
    </EmptyState>
  );
}

export function MatchesTab() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const [reloadKey, setReloadKey] = useState(0);
  const resultsRef = useRef<MatchResultsHandle>(null);

  if (activeProfile && activeProfile.kind !== "business") {
    return <PersonalGate />;
  }

  return (
    <div className="flex flex-col gap-4">
      <NeedsManager onChanged={() => setReloadKey((n) => n + 1)} />
      <PullToRefresh
        onRefresh={() => resultsRef.current?.refresh() ?? Promise.resolve()}
      >
        <MatchResults reloadKey={reloadKey} ref={resultsRef} />
      </PullToRefresh>
    </div>
  );
}
