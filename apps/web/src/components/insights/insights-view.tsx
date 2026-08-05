"use client";

import {
  Heart,
  MessageCircle,
  Repeat2,
  UserPlus,
  Eye as ViewsIcon,
  PenSquare,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import {
  EngagementChart,
  ViewsAreaChart,
} from "@/components/insights/insights-charts";
import { TYPE_BADGES } from "@/components/post-types/registry";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import { formatCompact } from "@/lib/ads/format";
import type {
  AnalyticsOverview,
  AnalyticsPostRow,
  Paginated,
  PostType,
} from "@/lib/api/types";

type Period = "7" | "30" | "90";
type Phase = "loading" | "loaded" | "error";

const PERIODS: { value: Period; label: string }[] = [
  { value: "7", label: "Last 7 days" },
  { value: "30", label: "Last 30 days" },
  { value: "90", label: "Last 90 days" },
];

function typeLabel(type: PostType): string {
  return TYPE_BADGES[type] ?? type.charAt(0).toUpperCase() + type.slice(1);
}

function StatTile({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Heart;
  label: string;
  value: number;
}) {
  return (
    <div className="flex flex-col gap-1 rounded-xl border bg-card p-3">
      <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Icon className="size-3.5" aria-hidden />
        {label}
      </span>
      <span className="text-xl font-semibold tabular-nums">
        {formatCompact(value)}
      </span>
    </div>
  );
}

interface InsightsState {
  /** The period this state belongs to (reset-during-render identity). */
  key: Period;
  phase: Phase;
  overview: AnalyticsOverview | null;
  posts: AnalyticsPostRow[];
}

export function InsightsView() {
  const [period, setPeriod] = useState<Period>("7");
  const [state, setState] = useState<InsightsState>({
    key: "7",
    phase: "loading",
    overview: null,
    posts: [],
  });

  // Reset while rendering when the period changes (no setState in effect).
  if (state.key !== period) {
    setState({ key: period, phase: "loading", overview: null, posts: [] });
  }

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      api.get<{ data: AnalyticsOverview }>(
        `/api/v1/analytics/overview?days=${period}`,
      ),
      api.get<Paginated<AnalyticsPostRow>>(
        `/api/v1/analytics/posts?days=${period}`,
      ),
    ])
      .then(([overviewRes, postsRes]) => {
        if (cancelled) return;
        setState({
          key: period,
          phase: "loaded",
          overview: overviewRes.data,
          posts: [...postsRes.data].sort((a, b) => b.views - a.views).slice(0, 8),
        });
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key: period, phase: "error", overview: null, posts: [] });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [period]);

  const { phase, overview, posts } = state;
  const totals = overview?.totals;
  const series = overview?.series ?? [];
  const hasData =
    series.length > 0 ||
    (totals != null &&
      Object.values(totals).some((value) => Number(value) > 0));

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold tracking-tight">Insights</h1>
        <Select
          value={period}
          onValueChange={(value) => setPeriod(value as Period)}
        >
          <SelectTrigger className="w-36" aria-label="Time period">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {PERIODS.map((p) => (
              <SelectItem key={p.value} value={p.value}>
                {p.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {phase === "loading" ? (
        <div className="flex flex-col gap-5">
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-20 w-full rounded-xl" />
            ))}
          </div>
          <Skeleton className="h-64 w-full rounded-xl" />
          <Skeleton className="h-64 w-full rounded-xl" />
        </div>
      ) : phase === "error" ? (
        <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
          Couldn&apos;t load your insights.
        </div>
      ) : !hasData || !totals ? (
        <EmptyState
          icon={PenSquare}
          title="No data yet — post something!"
          description="Once your posts start getting views and engagement, your stats will appear here."
        />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <StatTile icon={ViewsIcon} label="Views" value={totals.views} />
            <StatTile icon={Heart} label="Likes" value={totals.likes} />
            <StatTile
              icon={MessageCircle}
              label="Comments"
              value={totals.comments}
            />
            <StatTile icon={Repeat2} label="Reposts" value={totals.reposts} />
            <StatTile
              icon={UserPlus}
              label="Followers"
              value={totals.followers_gained}
            />
            <StatTile
              icon={PenSquare}
              label="Posts"
              value={totals.posts_published}
            />
          </div>

          <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <h2 className="text-sm font-semibold">Views over time</h2>
            <ViewsAreaChart data={series} />
          </section>

          <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <h2 className="text-sm font-semibold">Engagement</h2>
            <EngagementChart data={series} />
          </section>

          {posts.length > 0 ? (
            <section className="flex flex-col gap-3">
              <h2 className="text-sm font-semibold">Top posts</h2>
              <ol className="flex flex-col gap-2">
                {posts.map((post, index) => (
                  <li key={post.ulid}>
                    <Link
                      href={`/p/${post.ulid}`}
                      className="flex items-center gap-3 rounded-xl border bg-card p-3 transition-colors hover:bg-accent/40"
                    >
                      <span className="w-5 shrink-0 text-center text-sm font-semibold text-muted-foreground tabular-nums">
                        {index + 1}
                      </span>
                      <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <Badge variant="secondary" className="w-fit text-[10px]">
                          {typeLabel(post.type)}
                        </Badge>
                        <p className="line-clamp-1 text-sm">
                          {post.body?.trim() || `${typeLabel(post.type)} post`}
                        </p>
                      </div>
                      <div className="flex shrink-0 flex-col items-end gap-1">
                        <span className="flex items-center gap-1 text-xs text-muted-foreground tabular-nums">
                          <ViewsIcon className="size-3.5" aria-hidden />
                          {formatCompact(post.views)}
                        </span>
                        <Badge variant="outline" className="text-[10px]">
                          {post.engagement_rate_pct.toFixed(1)}%
                        </Badge>
                      </div>
                    </Link>
                  </li>
                ))}
              </ol>
            </section>
          ) : null}
        </>
      )}
    </div>
  );
}
