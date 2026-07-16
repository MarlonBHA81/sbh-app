"use client";

import {
  ArrowLeft,
  BadgeCheck,
  Bookmark,
  Building2,
  CalendarClock,
  ExternalLink,
  FileWarning,
  MapPin,
  Tag,
  Wallet,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Opportunity } from "@/lib/api/types";
import { closesLabel, opportunityTypeLabel } from "@/lib/opportunities";
import { cn } from "@/lib/utils";

export function OpportunityDetail({ ulid }: { ulid: string }) {
  const [state, setState] = useState<{
    phase: "loading" | "loaded" | "error";
    opportunity: Opportunity | null;
  }>({ phase: "loading", opportunity: null });
  const [saved, setSaved] = useState(false);
  const [saveBusy, setSaveBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Opportunity }>(`/api/v1/opportunities/${ulid}`)
      .then((res) => {
        if (cancelled) return;
        setState({ phase: "loaded", opportunity: res.data });
        setSaved(res.data.is_saved);
      })
      .catch(() => {
        if (!cancelled) setState({ phase: "error", opportunity: null });
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  async function toggleSave() {
    if (saveBusy) return;
    setSaveBusy(true);
    const next = !saved;
    setSaved(next);
    try {
      if (next) await api.post(`/api/v1/opportunities/${ulid}/save`);
      else await api.del(`/api/v1/opportunities/${ulid}/save`);
    } catch {
      setSaved(!next);
      toast.error("Couldn't update — try again.");
    } finally {
      setSaveBusy(false);
    }
  }

  const meta = [
    { icon: Building2, value: state.opportunity?.organisation },
    { icon: MapPin, value: state.opportunity?.province },
    { icon: Tag, value: state.opportunity?.industry },
    { icon: Wallet, value: state.opportunity?.amount },
  ].filter((row) => Boolean(row.value));

  return (
    <div className="flex flex-col gap-4">
      <div className="-mx-4 -mt-4 flex items-center gap-2 border-b bg-background/90 px-2 py-2 backdrop-blur md:-mt-6">
        <Button variant="ghost" size="icon" className="size-10 rounded-full" asChild>
          <Link href="/opportunities" aria-label="Back">
            <ArrowLeft className="size-5" aria-hidden />
          </Link>
        </Button>
        <h1 className="text-base font-semibold">Opportunity</h1>
      </div>

      {state.phase === "loading" ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-8 w-3/4" />
          <Skeleton className="h-40 w-full rounded-(--radius-card)" />
        </div>
      ) : null}

      {state.phase === "error" ? (
        <EmptyState
          icon={FileWarning}
          title="Opportunity not found"
          description="It may have closed or been removed."
        >
          <Button asChild variant="outline" className="mt-2 h-11">
            <Link href="/opportunities">Back to opportunities</Link>
          </Button>
        </EmptyState>
      ) : null}

      {state.phase === "loaded" && state.opportunity ? (
        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-2">
            <span className="rounded-full bg-teal/12 px-2.5 py-0.5 text-[11px] font-medium text-teal-text">
              {opportunityTypeLabel(state.opportunity.type)}
            </span>
            {state.opportunity.is_official ? (
              <span className="flex items-center gap-1 rounded-full bg-sage/15 px-2 py-0.5 text-[11px] font-medium text-sage-ink">
                <BadgeCheck className="size-3" aria-hidden />
                Official
              </span>
            ) : null}
            <span className="flex items-center gap-1 text-[12px] text-text-secondary">
              <CalendarClock className="size-3.5" aria-hidden />
              {closesLabel(state.opportunity.closes_at)}
            </span>
          </div>

          <h2 className="font-heading text-xl leading-snug font-semibold text-text-primary">
            {state.opportunity.title}
          </h2>

          {meta.length > 0 ? (
            <dl className="flex flex-col gap-2 rounded-(--radius-card) border border-warmgray bg-card p-4">
              {meta.map((row, i) => {
                const Icon = row.icon;
                return (
                  <div key={i} className="flex items-center gap-2 text-sm">
                    <Icon className="size-4 shrink-0 text-text-secondary" aria-hidden />
                    <span className="text-text-primary">{row.value}</span>
                  </div>
                );
              })}
            </dl>
          ) : null}

          <p className="text-[15px] leading-relaxed whitespace-pre-wrap text-text-primary">
            {state.opportunity.description}
          </p>

          <div className="flex flex-col gap-2 sm:flex-row">
            {state.opportunity.url ? (
              <Button asChild className="h-11 flex-1">
                <a href={state.opportunity.url} target="_blank" rel="noopener noreferrer">
                  Apply / read more
                  <ExternalLink className="size-4" aria-hidden />
                </a>
              </Button>
            ) : null}
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              disabled={saveBusy}
              onClick={() => void toggleSave()}
            >
              <Bookmark
                className={cn("size-4", saved && "fill-teal text-teal")}
                aria-hidden
              />
              {saved ? "Saved" : "Save"}
            </Button>
          </div>

          {state.opportunity.source ? (
            <p className="flex flex-wrap items-center gap-1 text-xs text-text-secondary">
              {state.opportunity.is_official ? (
                <BadgeCheck className="size-3.5 text-sage-ink" aria-hidden />
              ) : null}
              Source:{" "}
              {state.opportunity.source_url ? (
                <a
                  href={state.opportunity.source_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-medium text-teal-text hover:underline"
                >
                  {state.opportunity.source}
                </a>
              ) : (
                <span className="font-medium text-text-primary">
                  {state.opportunity.source}
                </span>
              )}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
