"use client";

import { CheckCircle2, Circle, Clock, GraduationCap } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Lesson, LessonProgress } from "@/lib/api/types";

interface TrackGroup {
  title: string;
  lessons: Lesson[];
}

/** Group lessons by their track, preserving server order; standalone last. */
function groupByTrack(lessons: Lesson[]): TrackGroup[] {
  const groups: TrackGroup[] = [];
  const index = new Map<string, TrackGroup>();

  for (const lesson of lessons) {
    const title = lesson.track?.title ?? "More lessons";
    let group = index.get(title);
    if (!group) {
      group = { title, lessons: [] };
      index.set(title, group);
      groups.push(group);
    }
    group.lessons.push(lesson);
  }
  return groups;
}

function LessonRow({ lesson }: { lesson: Lesson }) {
  return (
    <Link
      href={`/learn/${lesson.ulid}`}
      className="flex items-center gap-3 py-3 active:scale-[0.99]"
    >
      {lesson.is_completed ? (
        <CheckCircle2 className="size-5 shrink-0 text-teal" aria-hidden />
      ) : (
        <Circle className="size-5 shrink-0 text-text-secondary" aria-hidden />
      )}
      <span className="flex flex-col">
        <span className="text-sm font-medium text-text-primary">
          {lesson.title}
        </span>
        <span className="flex items-center gap-1 text-[11px] text-text-secondary">
          <Clock className="size-3" aria-hidden />
          {lesson.minutes} min
        </span>
      </span>
    </Link>
  );
}

/** Learn hub (V2 · LEARN): tracks of bite-size lessons with member progress. */
export function LearnView() {
  const [lessons, setLessons] = useState<Lesson[] | null>(null);
  const [progress, setProgress] = useState<LessonProgress | null>(null);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      api.get<{ data: Lesson[] }>("/api/v1/learn/lessons"),
      api.get<{ data: LessonProgress }>("/api/v1/me/learn/progress"),
    ])
      .then(([lessonsRes, progressRes]) => {
        if (cancelled) return;
        setLessons(lessonsRes.data);
        setProgress(progressRes.data);
      })
      .catch(() => {
        if (!cancelled) setLessons([]);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  // UX pattern 2 (goal gradient): never render an empty bar — floor to a sliver
  // while the caption stays truthful.
  const pct =
    progress && progress.total > 0
      ? Math.round((progress.completed / progress.total) * 100)
      : 0;
  const barWidth = `${Math.max(pct, 6)}%`;

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Learn" />
      <p className="text-sm text-text-secondary">
        Short, practical lessons — about five minutes each.
      </p>

      {progress && progress.total > 0 ? (
        <div className="flex flex-col gap-2 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
          <div className="flex items-center justify-between text-sm">
            <span className="font-medium text-text-primary">Your progress</span>
            <span className="text-text-secondary">
              {progress.completed} of {progress.total} complete
            </span>
          </div>
          <div className="h-2 w-full overflow-hidden rounded-full bg-warmgray/60">
            <div
              className="h-full rounded-full bg-teal transition-[width]"
              style={{ width: barWidth }}
            />
          </div>
        </div>
      ) : null}

      {lessons === null ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full rounded-(--radius-card)" />
          ))}
        </div>
      ) : lessons.length === 0 ? (
        <EmptyState
          icon={GraduationCap}
          title="No lessons yet"
          description="Bite-size lessons will appear here as they're added."
        />
      ) : (
        <div className="flex flex-col gap-4">
          {groupByTrack(lessons).map((group) => (
            <section
              key={group.title}
              className="flex flex-col rounded-(--radius-card) border border-warmgray bg-card px-4 shadow-card"
            >
              <h2 className="border-b border-warmgray py-3 font-heading text-[15px] font-semibold text-text-primary">
                {group.title}
              </h2>
              <ul className="flex flex-col divide-y divide-warmgray">
                {group.lessons.map((lesson) => (
                  <li key={lesson.ulid}>
                    <LessonRow lesson={lesson} />
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
