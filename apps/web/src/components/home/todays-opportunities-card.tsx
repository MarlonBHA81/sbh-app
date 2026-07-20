"use client";

import { CalendarClock, ChevronRight, Sparkles } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import * as api from "@/lib/api/client";
import type { Opportunity } from "@/lib/api/types";
import { closesLabel, opportunityTypeLabel } from "@/lib/opportunities";

/**
 * "Today's Opportunities" (V1 · Daily Home; V3 · personalised Home): the next
 * few opportunities, fit-ranked to the member's industry, closing date and
 * official/partner status via /me/opportunities/suggested. Self-hides when none.
 */
export function TodaysOpportunitiesCard() {
  const [items, setItems] = useState<Opportunity[] | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const res = await api.get<{ data: Opportunity[] }>(
          "/api/v1/me/opportunities/suggested",
        );
        if (!cancelled) setItems(res.data.slice(0, 3));
      } catch {
        if (!cancelled) setItems([]);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

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
              {o.fit_reason ? (
                <span className="text-[11px] text-sage-ink">{o.fit_reason}</span>
              ) : null}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
