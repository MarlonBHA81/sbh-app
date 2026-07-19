"use client";

import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Clock,
  ExternalLink,
  FileWarning,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Lesson, LessonRef } from "@/lib/api/types";

export function LessonView({ ulid }: { ulid: string }) {
  const [state, setState] = useState<{
    phase: "loading" | "loaded" | "error";
    lesson: Lesson | null;
    next: LessonRef | null;
  }>({ phase: "loading", lesson: null, next: null });
  const [completed, setCompleted] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Lesson; next: LessonRef | null }>(
        `/api/v1/learn/lessons/${ulid}`,
      )
      .then((res) => {
        if (cancelled) return;
        setState({ phase: "loaded", lesson: res.data, next: res.next });
        setCompleted(res.data.is_completed);
      })
      .catch(() => {
        if (!cancelled)
          setState({ phase: "error", lesson: null, next: null });
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  async function markComplete() {
    if (busy || completed) return;
    setBusy(true);
    try {
      await api.post(`/api/v1/learn/lessons/${ulid}/complete`);
      setCompleted(true);
      toast.success("Lesson complete — nice work.");
    } catch {
      toast.error("Couldn't save — try again.");
    } finally {
      setBusy(false);
    }
  }

  const lesson = state.lesson;

  return (
    <div className="flex flex-col gap-4">
      <div className="-mx-4 -mt-4 flex items-center gap-2 border-b bg-background/90 px-2 py-2 backdrop-blur md:-mt-6">
        <Button variant="ghost" size="icon" className="size-10 rounded-full" asChild>
          <Link href="/learn" aria-label="Back">
            <ArrowLeft className="size-5" aria-hidden />
          </Link>
        </Button>
        <h1 className="text-base font-semibold">Lesson</h1>
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
          title="Lesson not found"
          description="It may have been removed."
        >
          <Button asChild variant="outline" className="mt-2 h-11">
            <Link href="/learn">Back to Learn</Link>
          </Button>
        </EmptyState>
      ) : null}

      {state.phase === "loaded" && lesson ? (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-2 text-[12px] text-text-secondary">
            {lesson.track ? (
              <span className="rounded-full bg-teal/12 px-2.5 py-0.5 font-medium text-teal-text">
                {lesson.track.title}
              </span>
            ) : null}
            <span className="flex items-center gap-1">
              <Clock className="size-3.5" aria-hidden />
              {lesson.minutes} min
            </span>
          </div>

          <h2 className="font-heading text-xl leading-snug font-semibold text-text-primary">
            {lesson.title}
          </h2>

          {lesson.body ? (
            <p className="text-[15px] leading-relaxed whitespace-pre-wrap text-text-primary">
              {lesson.body}
            </p>
          ) : null}

          {lesson.external_url ? (
            <Button asChild variant="outline" className="h-11">
              <a
                href={lesson.external_url}
                target="_blank"
                rel="noopener noreferrer"
              >
                Open the full lesson
                <ExternalLink className="size-4" aria-hidden />
              </a>
            </Button>
          ) : null}

          {completed ? (
            <div className="flex items-center gap-2 rounded-(--radius-card) border border-teal/30 bg-teal/8 p-4 text-sm font-medium text-teal-text">
              <CheckCircle2 className="size-5" aria-hidden />
              Completed
            </div>
          ) : (
            <Button
              type="button"
              className="h-11"
              disabled={busy}
              onClick={() => void markComplete()}
            >
              {busy ? "Saving…" : "Mark as complete"}
            </Button>
          )}

          {state.next ? (
            <Button asChild variant="outline" className="h-11">
              <Link href={`/learn/${state.next.ulid}`}>
                Next: {state.next.title}
                <ArrowRight className="size-4" aria-hidden />
              </Link>
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
