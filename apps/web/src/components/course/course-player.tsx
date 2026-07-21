"use client";

import {
  ArrowLeft,
  CheckCircle2,
  Circle,
  Download,
  Lock,
  PlayCircle,
} from "lucide-react";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type {
  CourseLesson,
  CourseOutline,
  CourseOutlineLesson,
} from "@/lib/api/types";
import {
  downloadLessonAttachment,
  videoEmbedUrl,
} from "@/lib/shop";

/** Course player (Shop P3): module/lesson tree + gated lesson content + progress. */
export function CoursePlayer({ ulid }: { ulid: string }) {
  const [outline, setOutline] = useState<CourseOutline | null>(null);
  const [missing, setMissing] = useState(false);
  const [activeUlid, setActiveUlid] = useState<string | null>(null);

  // Refresh the outline after a completion (updates progress); only ever called
  // from event handlers, never inside an effect.
  const reload = useCallback(async () => {
    try {
      const res = await api.get<{ data: CourseOutline }>(
        `/api/v1/shop/products/${ulid}/curriculum`,
      );
      setOutline(res.data);
    } catch {
      setMissing(true);
    }
  }, [ulid]);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: CourseOutline }>(`/api/v1/shop/products/${ulid}/curriculum`)
      .then((res) => {
        if (cancelled) return;
        setOutline(res.data);
        // Auto-open the first lesson the viewer can access.
        const first = res.data.modules
          .flatMap((m) => m.lessons)
          .find((l) => res.data.owned || l.is_preview);
        if (first) setActiveUlid(first.ulid);
      })
      .catch(() => {
        if (!cancelled) setMissing(true);
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  if (missing) {
    return (
      <div className="flex flex-col gap-3">
        <BackLink outline={null} />
        <p className="text-sm text-text-secondary">
          This course isn&apos;t available.
        </p>
      </div>
    );
  }

  if (!outline) {
    return <Skeleton className="h-72 w-full rounded-xl" />;
  }

  const lessons = outline.modules.flatMap((m) => m.lessons);
  const canOpen = (l: CourseOutlineLesson) => outline.owned || l.is_preview;
  const pct =
    outline.progress.total === 0
      ? 0
      : Math.round((outline.progress.completed / outline.progress.total) * 100);

  return (
    <div className="flex flex-col gap-4">
      <BackLink outline={outline} />

      <div>
        <h1 className="font-heading text-xl font-semibold text-text-primary">
          {outline.product.title}
        </h1>
        {outline.owned ? (
          <div className="mt-2 flex flex-col gap-1">
            <div className="h-2 w-full overflow-hidden rounded-full bg-sage/20">
              <div
                className="h-full rounded-full bg-teal transition-[width]"
                style={{ width: `${Math.max(pct, outline.progress.completed > 0 ? 6 : 0)}%` }}
              />
            </div>
            <span className="text-[12px] text-text-secondary">
              {outline.progress.completed}/{outline.progress.total} lessons
              complete
            </span>
          </div>
        ) : (
          <p className="mt-1 text-[13px] text-text-secondary">
            Preview this course. Purchase it to unlock every lesson.
          </p>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-[260px_1fr]">
        {/* Curriculum tree */}
        <nav className="flex flex-col gap-3">
          {outline.modules.map((module) => (
            <div key={module.ulid} className="flex flex-col gap-1">
              <h2 className="text-[13px] font-semibold text-text-primary">
                {module.title}
              </h2>
              <ul className="flex flex-col">
                {module.lessons.map((lesson) => {
                  const open = canOpen(lesson);
                  const active = lesson.ulid === activeUlid;
                  return (
                    <li key={lesson.ulid}>
                      <button
                        type="button"
                        disabled={!open}
                        onClick={() => setActiveUlid(lesson.ulid)}
                        className={`flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-[13px] ${
                          active
                            ? "bg-teal/12 text-teal-text"
                            : "text-text-secondary hover:bg-sage/10"
                        } ${open ? "" : "opacity-60"}`}
                      >
                        {lesson.is_completed ? (
                          <CheckCircle2 className="size-4 shrink-0 text-teal" aria-hidden />
                        ) : open ? (
                          <Circle className="size-4 shrink-0" aria-hidden />
                        ) : (
                          <Lock className="size-4 shrink-0" aria-hidden />
                        )}
                        <span className="flex-1 truncate">{lesson.title}</span>
                        {lesson.is_preview && !outline.owned ? (
                          <span className="text-[10px] font-medium uppercase text-teal-text">
                            Free
                          </span>
                        ) : null}
                      </button>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>

        {/* Lesson pane */}
        <div className="min-w-0">
          {activeUlid ? (
            <LessonPane
              key={activeUlid}
              lessonUlid={activeUlid}
              onCompleted={reload}
              onNext={(next) => setActiveUlid(next)}
            />
          ) : (
            <div className="rounded-(--radius-card) border border-warmgray bg-card p-6 text-center text-sm text-text-secondary shadow-card">
              {lessons.length === 0
                ? "The instructor hasn't added lessons yet."
                : "Select a lesson to begin."}
              {!outline.owned && outline.product.store ? (
                <div className="mt-3">
                  <Button asChild className="h-10">
                    <Link
                      href={`/shop/${outline.product.store}/${outline.product.ulid}`}
                    >
                      Get this course
                    </Link>
                  </Button>
                </div>
              ) : null}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function LessonPane({
  lessonUlid,
  onCompleted,
  onNext,
}: {
  lessonUlid: string;
  onCompleted: () => void;
  onNext: (ulid: string) => void;
}) {
  const [lesson, setLesson] = useState<CourseLesson | null>(null);
  const [locked, setLocked] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    // The parent keys this component by lessonUlid, so it remounts per lesson —
    // initial state is already reset, no synchronous reset needed here.
    let cancelled = false;
    api
      .get<{ data: CourseLesson }>(`/api/v1/me/courses/lessons/${lessonUlid}`)
      .then((res) => {
        if (!cancelled) setLesson(res.data);
      })
      .catch((err) => {
        if (!cancelled) setLocked(err instanceof api.ApiError && err.status === 403);
      });
    return () => {
      cancelled = true;
    };
  }, [lessonUlid]);

  if (locked) {
    return (
      <div className="rounded-(--radius-card) border border-warmgray bg-card p-6 text-center text-sm text-text-secondary shadow-card">
        <Lock className="mx-auto mb-2 size-6" aria-hidden />
        Purchase this course to unlock the lesson.
      </div>
    );
  }

  if (!lesson) return <Skeleton className="h-64 w-full rounded-xl" />;

  const embed = videoEmbedUrl(lesson.video_url);

  async function markComplete() {
    if (!lesson) return;
    setBusy(true);
    try {
      await api.post(`/api/v1/me/courses/lessons/${lesson.ulid}/complete`);
      onCompleted();
      if (lesson.next) onNext(lesson.next.ulid);
      else toast.success("Course complete — nicely done!");
    } catch {
      toast.error("Couldn't save your progress");
    } finally {
      setBusy(false);
    }
  }

  async function getAttachment() {
    if (!lesson) return;
    try {
      await downloadLessonAttachment(lesson.ulid, lesson.title);
    } catch {
      toast.error("That attachment isn't available");
    }
  }

  return (
    <article className="flex flex-col gap-4 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <h2 className="font-heading text-lg font-semibold text-text-primary">
        {lesson.title}
      </h2>

      {embed ? (
        <div className="aspect-video w-full overflow-hidden rounded-lg bg-black">
          <iframe
            src={embed}
            title={lesson.title}
            className="size-full"
            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        </div>
      ) : lesson.video_url ? (
        <Button asChild variant="outline" className="h-10 w-fit gap-1.5">
          <a href={lesson.video_url} target="_blank" rel="noopener noreferrer">
            <PlayCircle className="size-4" aria-hidden />
            Watch video
          </a>
        </Button>
      ) : null}

      {lesson.body ? (
        <p className="text-sm leading-relaxed whitespace-pre-wrap text-text-secondary">
          {lesson.body}
        </p>
      ) : null}

      {lesson.has_attachment ? (
        <Button
          type="button"
          variant="outline"
          className="h-10 w-fit gap-1.5"
          onClick={getAttachment}
        >
          <Download className="size-4" aria-hidden />
          Download attachment
        </Button>
      ) : null}

      <div className="flex items-center gap-2 border-t border-warmgray pt-3">
        <Button
          type="button"
          className="h-10"
          onClick={markComplete}
          disabled={busy}
        >
          {lesson.is_completed
            ? "Completed — next lesson"
            : busy
              ? "Saving…"
              : "Mark complete"}
        </Button>
        {lesson.is_completed ? (
          <CheckCircle2 className="size-5 text-teal" aria-hidden />
        ) : null}
      </div>
    </article>
  );
}

function BackLink({ outline }: { outline: CourseOutline | null }) {
  const href = outline?.product.store
    ? `/shop/${outline.product.store}/${outline.product.ulid}`
    : "/shop/purchases";
  return (
    <Link
      href={href}
      className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden />
      Back
    </Link>
  );
}
