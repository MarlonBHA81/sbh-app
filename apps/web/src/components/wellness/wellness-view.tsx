"use client";

import { HeartHandshake } from "lucide-react";
import { useEffect, useState } from "react";

import { CheckinPanel } from "@/components/wellness/checkin-panel";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { WellnessResource } from "@/lib/api/types";

/** Quiet, human labels for each resource grouping. */
const CATEGORY_LABEL: Record<string, string> = {
  reflection: "A moment to reflect",
  encouragement: "A word of encouragement",
  rest: "Permission to rest",
  connection: "On staying connected",
  focus: "Finding your focus",
};

function ResourceCard({ resource }: { resource: WellnessResource }) {
  return (
    <article className="flex flex-col gap-1.5 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <span className="text-[11px] font-medium tracking-wide text-sage-ink uppercase">
        {CATEGORY_LABEL[resource.category] ?? "A gentle note"}
      </span>
      <h3 className="font-heading text-[16px] leading-snug font-semibold text-text-primary">
        {resource.title}
      </h3>
      <p className="text-sm leading-relaxed whitespace-pre-wrap text-text-secondary">
        {resource.body}
      </p>
    </article>
  );
}

/**
 * Wellness & resilience space (V3 · BELONG). A calm, supportive corner: a
 * private "how are you doing?" check-in, then admin-curated encouragement.
 * Deliberately un-gamified — no streaks, no points, no pressure.
 */
export function WellnessView() {
  const [resources, setResources] = useState<WellnessResource[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: WellnessResource[] }>("/api/v1/wellness/resources")
      .then((res) => {
        if (!cancelled) setResources(res.data);
      })
      .catch(() => {
        if (!cancelled) setResources([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex flex-col gap-5">
      <ScreenHeader title="A moment for you" />
      <div className="flex items-start gap-3 rounded-(--radius-card) bg-sage/10 p-4">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sage/20 text-sage-ink">
          <HeartHandshake className="size-5" aria-hidden />
        </span>
        <p className="text-sm leading-relaxed text-text-secondary">
          Running a business takes a lot out of you. This is a quiet space — no
          targets, no scores. Check in with yourself, and take what helps.
        </p>
      </div>

      <CheckinPanel />

      <section className="flex flex-col gap-3">
        {resources === null ? (
          <>
            <Skeleton className="h-28 w-full rounded-(--radius-card)" />
            <Skeleton className="h-28 w-full rounded-(--radius-card)" />
          </>
        ) : (
          resources.map((r) => <ResourceCard key={r.ulid} resource={r} />)
        )}
      </section>
    </div>
  );
}
