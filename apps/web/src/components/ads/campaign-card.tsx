"use client";

import {
  BarChart3,
  MousePointerClick,
  Pause,
  Play,
  StopCircle,
} from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";

import { TYPE_BADGES } from "@/components/post-types/registry";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import * as api from "@/lib/api/client";
import { formatCompact, formatRand } from "@/lib/ads/format";
import type { Campaign, CampaignStatus, PostType } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const STATUS_META: Record<
  CampaignStatus,
  { label: string; className: string }
> = {
  active: {
    label: "Active",
    className:
      "border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400",
  },
  paused: {
    label: "Paused",
    className:
      "border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-400",
  },
  completed: {
    label: "Completed",
    className: "border-transparent bg-muted text-muted-foreground",
  },
};

function typeLabel(type: PostType): string {
  return TYPE_BADGES[type] ?? type.charAt(0).toUpperCase() + type.slice(1);
}

/** Whole days from now until `iso`, floored at 0. */
function daysRemaining(iso: string): number {
  const end = new Date(iso).getTime();
  if (Number.isNaN(end)) return 0;
  return Math.max(0, Math.ceil((end - Date.now()) / 86_400_000));
}

function Stat({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof BarChart3;
  label: string;
  value: string;
}) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="flex items-center gap-1 text-xs text-muted-foreground">
        <Icon className="size-3.5" aria-hidden />
        {label}
      </span>
      <span className="text-sm font-semibold tabular-nums">{value}</span>
    </div>
  );
}

export function CampaignCard({
  campaign,
  onUpdated,
  showDetailsLink = true,
}: {
  campaign: Campaign;
  onUpdated?: (campaign: Campaign) => void;
  /** Hide the "View details" link (e.g. when already on the detail page). */
  showDetailsLink?: boolean;
}) {
  const [busy, setBusy] = useState(false);
  const [endOpen, setEndOpen] = useState(false);
  const status = STATUS_META[campaign.status];
  const spentPct =
    campaign.budget_cents > 0
      ? Math.min(100, (campaign.spent_cents / campaign.budget_cents) * 100)
      : 0;
  const remaining = daysRemaining(campaign.ends_at);

  async function toggleStatus() {
    if (busy) return;
    const next = campaign.status === "active" ? "paused" : "active";
    setBusy(true);
    try {
      const res = await api.patch<{ data: Campaign }>(
        `/api/v1/ads/campaigns/${campaign.ulid}`,
        { status: next },
      );
      onUpdated?.(res.data);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update",
      );
    } finally {
      setBusy(false);
    }
  }

  async function endEarly() {
    if (busy) return;
    setBusy(true);
    try {
      await api.del(`/api/v1/ads/campaigns/${campaign.ulid}`);
      onUpdated?.({ ...campaign, status: "completed" });
      setEndOpen(false);
      toast.success("Campaign ended");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't end campaign",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <article className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-1.5">
          <Badge variant="secondary" className="w-fit text-[10px]">
            {typeLabel(campaign.post.type)}
          </Badge>
          <p className="line-clamp-1 text-sm font-medium">
            {campaign.post.body?.trim() || `${typeLabel(campaign.post.type)} post`}
          </p>
        </div>
        <Badge className={cn("shrink-0", status.className)}>
          {status.label}
        </Badge>
      </div>

      <div className="flex flex-col gap-1.5">
        <Progress value={spentPct} />
        <div className="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
          <span>
            {formatRand(campaign.spent_cents)} of{" "}
            {formatRand(campaign.budget_cents)}
          </span>
          <span>{Math.round(spentPct)}%</span>
        </div>
      </div>

      <div className="grid grid-cols-3 gap-3">
        <Stat
          icon={BarChart3}
          label="Impressions"
          value={formatCompact(campaign.impressions)}
        />
        <Stat
          icon={MousePointerClick}
          label="Clicks"
          value={formatCompact(campaign.clicks)}
        />
        <Stat
          icon={BarChart3}
          label="CTR"
          value={`${campaign.ctr_pct.toFixed(1)}%`}
        />
      </div>

      <p className="text-xs text-muted-foreground">
        {campaign.status === "completed"
          ? "Campaign ended"
          : remaining > 0
            ? `${remaining} ${remaining === 1 ? "day" : "days"} remaining`
            : "Ending today"}
      </p>

      <div className="flex flex-wrap gap-2">
        {campaign.status !== "completed" ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="h-9"
            disabled={busy}
            onClick={() => void toggleStatus()}
          >
            {campaign.status === "active" ? (
              <>
                <Pause className="size-4" aria-hidden />
                Pause
              </>
            ) : (
              <>
                <Play className="size-4" aria-hidden />
                Resume
              </>
            )}
          </Button>
        ) : null}
        {campaign.status !== "completed" ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="h-9 text-destructive hover:text-destructive"
            disabled={busy}
            onClick={() => setEndOpen(true)}
          >
            <StopCircle className="size-4" aria-hidden />
            End early
          </Button>
        ) : null}
        {showDetailsLink ? (
          <Button asChild variant="ghost" size="sm" className="ml-auto h-9">
            <Link href={`/ads/${campaign.ulid}`}>View details</Link>
          </Button>
        ) : null}
      </div>

      <AlertDialog open={endOpen} onOpenChange={setEndOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>End this campaign?</AlertDialogTitle>
            <AlertDialogDescription>
              Promotion stops immediately and the remaining budget won&apos;t be
              spent. This can&apos;t be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={busy}
              onClick={(event) => {
                event.preventDefault();
                void endEarly();
              }}
            >
              {busy ? "Ending…" : "End campaign"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </article>
  );
}
