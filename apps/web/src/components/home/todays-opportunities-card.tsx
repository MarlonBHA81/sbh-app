"use client";

import { CalendarClock, ChevronRight, Sparkles } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import * as api from "@/lib/api/client";
import type { Opportunity, Paginated } from "@/lib/api/types";
import { closesLabel, opportunityTypeLabel } from "@/lib/opportunities";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

/**
 * "Today's Opportunities" (V1 · Daily Home): the next few opportunities,
 * personalised to the member's industry when set, with a graceful fallback to
 * the general feed so the card is never empty when opportunities exist. Reuses
 * the opportunities API — no new backend.
 */
export function TodaysOpportunitiesCard() {
  const industry = useAuthStore((s) => s.activeProfile?.category ?? null);
  const [items, setItems] = useState<Opportunity[] | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const general = "/api/v1/opportunities";
      const url = industry
        ? `${general}?industry=${encodeURIComponent(industry)}`
        : general;
      try {
        const res = await api.get<Paginated<Opportunity>>(url);
        let data = res.data;
        // Fall back to the general feed if the industry filter came back empty.
        if (data.length === 0 && industry) {
          data = (await api.get<Paginated<Opportunity>>(general)).data;
        }
        if (!cancelled) setItems(data.slice(0, 3));
      } catch {
        if (!cancelled) setItems([]);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [industry]);

  if (!items || items.length === 0) return null;

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <span className="flex size-8 items-center justify-center rounded-full bg-sage/15 text-sage-ink">
          <Sparkles className="size-4" aria-hidden />
        </span>
        <h2 className="font-heading text-[15px] font-semibold text-text-primary">
          Opportunities for you
        </h2>
        <Link
          href="/opportunities"
          className="ms-auto flex items-center text-[13px] font-medium text-teal-text hover:underline"
        >
          See all
          <ChevronRight className="size-4" aria-hidden />
        </Link>
      </div>

      <ul className="flex flex-col divide-y divide-warmgray">
        {items.map((o) => (
          <li key={o.ulid}>
            <Link
              href={`/opportunities/${o.ulid}`}
              className="flex flex-col gap-1 py-2.5 active:scale-[0.99]"
            >
              <div className="flex items-center gap-2">
                <span className="rounded-full bg-teal/12 px-2 py-0.5 text-[10px] font-medium text-teal-text">
                  {opportunityTypeLabel(o.type)}
                </span>
                <span className="flex items-center gap-1 text-[11px] text-text-secondary">
                  <CalendarClock className="size-3" aria-hidden />
                  {closesLabel(o.closes_at)}
                </span>
              </div>
              <span className="text-sm font-medium text-text-primary">
                {o.title}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
