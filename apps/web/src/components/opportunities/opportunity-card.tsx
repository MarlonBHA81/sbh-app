"use client";

import {
  Bookmark,
  BadgeCheck,
  Building2,
  CalendarClock,
  MapPin,
  Wallet,
} from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { Opportunity } from "@/lib/api/types";
import { closesLabel, opportunityTypeLabel } from "@/lib/opportunities";
import { cn } from "@/lib/utils";

/**
 * A single opportunity in the feed (V1 · GROW). Whole card links to the
 * detail; the save toggle is optimistic and calls the save/unsave endpoints.
 */
export function OpportunityCard({
  opportunity,
  onSavedChange,
}: {
  opportunity: Opportunity;
  /** Notify the list (e.g. so the Saved tab can drop an unsaved item). */
  onSavedChange?: (ulid: string, saved: boolean) => void;
}) {
  const [saved, setSaved] = useState(opportunity.is_saved);
  const [busy, setBusy] = useState(false);

  async function toggleSave() {
    if (busy) return;
    setBusy(true);
    const next = !saved;
    setSaved(next); // optimistic
    try {
      if (next) {
        await api.post(`/api/v1/opportunities/${opportunity.ulid}/save`);
      } else {
        await api.del(`/api/v1/opportunities/${opportunity.ulid}/save`);
      }
      onSavedChange?.(opportunity.ulid, next);
    } catch {
      setSaved(!next); // revert
      toast.error("Couldn't update — try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <article className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-start gap-2">
        <span className="rounded-full bg-teal/12 px-2.5 py-0.5 text-[11px] font-medium text-teal-text">
          {opportunityTypeLabel(opportunity.type)}
        </span>
        {opportunity.is_official ? (
          <span
            className="flex items-center gap-1 rounded-full bg-sage/15 px-2 py-0.5 text-[11px] font-medium text-sage-ink"
            title={
              opportunity.source
                ? `Verified source: ${opportunity.source}`
                : "Verified source"
            }
          >
            <BadgeCheck className="size-3" aria-hidden />
            Official
          </span>
        ) : null}
        {opportunity.province ? (
          <span className="flex items-center gap-1 text-[11px] text-text-secondary">
            <MapPin className="size-3" aria-hidden />
            {opportunity.province}
          </span>
        ) : null}
        <button
          type="button"
          onClick={toggleSave}
          disabled={busy}
          aria-pressed={saved}
          aria-label={saved ? "Saved — tap to remove" : "Save"}
          className="ms-auto flex size-9 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-accent active:scale-[0.98]"
        >
          <Bookmark
            className={cn("size-5", saved && "fill-teal text-teal")}
            aria-hidden
          />
        </button>
      </div>

      <Link href={`/opportunities/${opportunity.ulid}`} className="flex flex-col gap-2">
        <h3 className="font-heading text-[16px] leading-snug font-semibold text-text-primary">
          {opportunity.title}
        </h3>
        <p className="line-clamp-2 text-sm text-text-secondary">
          {opportunity.description}
        </p>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-text-secondary">
          {opportunity.organisation ? (
            <span className="flex items-center gap-1">
              <Building2 className="size-3.5" aria-hidden />
              {opportunity.organisation}
            </span>
          ) : null}
          {opportunity.amount ? (
            <span className="flex items-center gap-1 font-medium text-text-primary">
              <Wallet className="size-3.5" aria-hidden />
              {opportunity.amount}
            </span>
          ) : null}
          <span className="flex items-center gap-1">
            <CalendarClock className="size-3.5" aria-hidden />
            {closesLabel(opportunity.closes_at)}
          </span>
        </div>
      </Link>
    </article>
  );
}
