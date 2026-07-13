"use client";

import { ArrowLeft, LineChart } from "lucide-react";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";

import { CampaignCard } from "@/components/ads/campaign-card";
import { CampaignChart } from "@/components/ads/campaign-chart";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import { formatCompact } from "@/lib/ads/format";
import type { Campaign } from "@/lib/api/types";

type Phase = "loading" | "loaded" | "error";

function formatDate(iso: string): string {
  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) return iso;
  return parsed.toLocaleDateString("en-ZA", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

interface DetailState {
  /** The ulid this state belongs to (reset-during-render identity). */
  key: string;
  phase: Phase;
  campaign: Campaign | null;
}

export function CampaignDetailView({ ulid }: { ulid: string }) {
  const [state, setState] = useState<DetailState>({
    key: ulid,
    phase: "loading",
    campaign: null,
  });

  // Reset while rendering if the route param changes (no setState in effect).
  if (state.key !== ulid) {
    setState({ key: ulid, phase: "loading", campaign: null });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Campaign }>(`/api/v1/ads/campaigns/${ulid}?series=1`)
      .then((res) => {
        if (!cancelled) {
          setState({ key: ulid, phase: "loaded", campaign: res.data });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key: ulid, phase: "error", campaign: null });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  const { phase, campaign } = state;

  const handleUpdated = useCallback((updated: Campaign) => {
    // PATCH/DELETE responses omit the series/reach — keep those already loaded.
    setState((prev) => ({
      ...prev,
      campaign: {
        ...updated,
        series: prev.campaign?.series,
        reach: prev.campaign?.reach,
      },
    }));
  }, []);

  return (
    <div className="flex flex-col gap-4">
      <Link
        href="/ads"
        className="flex w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft className="size-4" aria-hidden />
        Ad Center
      </Link>

      {phase === "loading" ? (
        <>
          <Skeleton className="h-52 w-full rounded-xl" />
          <Skeleton className="h-64 w-full rounded-xl" />
        </>
      ) : phase === "error" || !campaign ? (
        <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
          Couldn&apos;t load this campaign.
        </div>
      ) : (
        <>
          <CampaignCard
            campaign={campaign}
            onUpdated={handleUpdated}
            showDetailsLink={false}
          />

          <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex items-center gap-2">
              <LineChart className="size-4 text-muted-foreground" aria-hidden />
              <h2 className="text-sm font-semibold">Daily reach</h2>
            </div>
            {campaign.series && campaign.series.length > 0 ? (
              <CampaignChart data={campaign.series} />
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No reach data yet — check back once the campaign has run for a
                day.
              </p>
            )}
          </section>

          <dl className="grid grid-cols-2 gap-3 rounded-xl border bg-card p-4 text-sm">
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">Started</dt>
              <dd className="font-medium">{formatDate(campaign.starts_at)}</dd>
            </div>
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">Ends</dt>
              <dd className="font-medium">{formatDate(campaign.ends_at)}</dd>
            </div>
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">Unique reach</dt>
              <dd className="font-medium tabular-nums">
                {campaign.reach !== undefined
                  ? formatCompact(campaign.reach)
                  : "—"}
              </dd>
            </div>
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">Link CTR</dt>
              <dd className="font-medium tabular-nums">
                {campaign.link_ctr_pct.toFixed(1)}%
              </dd>
            </div>
            <div className="col-span-2 flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">
                Engagement on the post
              </dt>
              <dd className="font-medium tabular-nums">
                {formatCompact(campaign.post.likes_count)} likes ·{" "}
                {formatCompact(campaign.post.comments_count)} comments ·{" "}
                {formatCompact(campaign.post.reposts_count)} reposts
              </dd>
            </div>
          </dl>
        </>
      )}
    </div>
  );
}
