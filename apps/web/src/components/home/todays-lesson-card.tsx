"use client";

import { ChevronRight, Clock, GraduationCap } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { HomeCard, type HomeCardTier } from "@/components/home/home-card";
import * as api from "@/lib/api/client";
import type { Lesson } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

/**
 * "Today's lesson" (V2 · LEARN): one bite-size lesson for the member, chosen by
 * their journey stage when set, with a graceful fallback to any lesson so the
 * card is never empty when lessons exist. Prefers an unfinished lesson. Reuses
 * the learn API — no new backend.
 */
export function TodaysLessonCard({ tier }: { tier?: HomeCardTier }) {
  const stage = useAuthStore((s) => s.activeProfile?.journey_stage ?? null);
  const [lesson, setLesson] = useState<Lesson | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const base = "/api/v1/learn/lessons";
      const url = stage
        ? `${base}?journey_stage=${encodeURIComponent(stage)}`
        : base;
      try {
        let data = (await api.get<{ data: Lesson[] }>(url)).data;
        if (data.length === 0 && stage) {
          data = (await api.get<{ data: Lesson[] }>(base)).data;
        }
        // Prefer a lesson the member hasn't finished yet.
        const pick = data.find((l) => !l.is_completed) ?? data[0] ?? null;
        if (!cancelled) setLesson(pick);
      } catch {
        if (!cancelled) setLesson(null);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [stage]);

  if (!lesson) return null;

  return (
    <HomeCard
      tier={tier}
      tone="plum"
      icon={GraduationCap}
      title="Today's lesson"
      aside={
        <Link
          href="/learn"
          className="flex items-center text-[13px] font-medium text-teal-text hover:underline"
        >
          All lessons
          <ChevronRight className="size-4" aria-hidden />
        </Link>
      }
    >
      <Link
        href={`/learn/${lesson.ulid}`}
        className="flex flex-col gap-1 active:scale-[0.99]"
      >
        <span className="text-sm font-medium text-text-primary">
          {lesson.title}
        </span>
        <span className="flex items-center gap-1 text-[11px] text-text-secondary">
          <Clock className="size-3" aria-hidden />
          {lesson.minutes} min{lesson.is_completed ? " · completed" : ""}
        </span>
      </Link>
    </HomeCard>
  );
}
