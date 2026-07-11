"use client";

import { Check } from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { Poll, PollOption, Post } from "@/lib/api/types";
import { useEchoChannel } from "@/lib/echo";
import { relativeTime } from "@/lib/time";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

interface PollVoteTalliedPayload {
  post_ulid: string;
  options: { id: number | string; votes_count: number }[];
  votes_count: number;
}

/** Recompute each option's percent from its votes and the new total. */
function withPercents(options: PollOption[], total: number): PollOption[] {
  return options.map((o) => ({
    ...o,
    percent: total > 0 ? Math.round((o.votes_count / total) * 100) : 0,
  }));
}

export function PollPost({ post, detail }: { post: Post; detail?: boolean }) {
  const [poll, setPoll] = useState<Poll | null>(post.poll ?? null);
  const busy = useRef(false);

  // Live tallies (detail page only) — additive totals, viewer choice untouched.
  useEchoChannel(detail && poll ? `post.${post.ulid}` : null, {
    PollVoteTallied: (p) => onTallied(p),
    ".PollVoteTallied": (p) => onTallied(p),
  });

  function onTallied(payload: unknown) {
    const data = payload as PollVoteTalliedPayload;
    if (!data || !Array.isArray(data.options)) return;
    setPoll((prev) => {
      if (!prev) return prev;
      const byId = new Map(data.options.map((o) => [String(o.id), o.votes_count]));
      const options = prev.options.map((o) => ({
        ...o,
        votes_count: byId.get(String(o.id)) ?? o.votes_count,
      }));
      return {
        ...prev,
        votes_count: data.votes_count,
        options: withPercents(options, data.votes_count),
      };
    });
  }

  if (!poll) return post.body ? <PostBody text={post.body} /> : null;

  const question = poll.question || post.body;
  const expired = poll.ends_at != null && new Date(poll.ends_at) < new Date();
  const voted = poll.viewer_option_id != null;
  const showResults = voted || expired;

  async function vote(optionId: number | string) {
    if (busy.current || voted || expired || !poll) return;
    busy.current = true;
    const previous = poll;

    const total = poll.votes_count + 1;
    const options = withPercents(
      poll.options.map((o) =>
        String(o.id) === String(optionId)
          ? { ...o, votes_count: o.votes_count + 1 }
          : o,
      ),
      total,
    );
    setPoll({
      ...poll,
      viewer_option_id: optionId,
      votes_count: total,
      options,
    });

    try {
      const res = await api.post<{ data: Post }>(
        `/api/v1/posts/${post.ulid}/poll-vote`,
        { option_id: optionId },
      );
      if (res.data.poll) setPoll(res.data.poll);
    } catch (error) {
      setPoll(previous);
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't record vote",
      );
    } finally {
      busy.current = false;
    }
  }

  return (
    <div className="flex flex-col gap-3" data-no-nav>
      {question ? <PostBody text={question} className="font-medium" /> : null}

      <div className="flex flex-col gap-2">
        {poll.options.map((option) => {
          const isChoice = String(poll.viewer_option_id) === String(option.id);
          if (!showResults) {
            return (
              <button
                key={String(option.id)}
                type="button"
                onClick={(event) => {
                  event.stopPropagation();
                  void vote(option.id);
                }}
                className="flex min-h-11 items-center rounded-lg border px-4 text-left text-sm font-medium transition-colors hover:border-primary hover:bg-accent/50"
              >
                {option.label}
              </button>
            );
          }
          return (
            <div
              key={String(option.id)}
              className="relative overflow-hidden rounded-lg border"
            >
              <div
                className={cn(
                  "absolute inset-y-0 left-0 transition-[width] duration-500 ease-out",
                  isChoice ? "bg-primary/25" : "bg-muted",
                )}
                style={{ width: `${option.percent}%` }}
                aria-hidden
              />
              <div className="relative flex min-h-11 items-center gap-2 px-4 py-2 text-sm">
                {isChoice ? (
                  <Check className="size-4 shrink-0 text-primary" aria-hidden />
                ) : null}
                <span className={cn("flex-1", isChoice && "font-semibold")}>
                  {option.label}
                </span>
                <span className="tabular-nums text-muted-foreground">
                  {option.percent}%
                </span>
              </div>
            </div>
          );
        })}
      </div>

      <p className="text-xs text-muted-foreground">
        {poll.votes_count} {poll.votes_count === 1 ? "vote" : "votes"}
        {poll.ends_at ? (
          <span> · {expired ? "Poll ended" : `ends ${relativeTime(poll.ends_at)}`}</span>
        ) : null}
      </p>
    </div>
  );
}
