"use client";

import { ChevronDown, Hash, X } from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { PostTopic, Topic } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export const MAX_POST_TOPICS = 3;

interface FlatTopic {
  topic: PostTopic;
  depth: number;
}

function flatten(topics: Topic[], depth = 0): FlatTopic[] {
  return topics.flatMap((topic) => [
    {
      topic: {
        id: topic.id,
        slug: topic.slug,
        name: topic.name,
        icon: topic.icon,
      },
      depth,
    },
    ...flatten(topic.children ?? [], depth + 1),
  ]);
}

/**
 * "Add topics" row for the composer: opens a popover listing the topic
 * tree (flattened with indentation) with checkboxes, max 3 selections.
 * Selected topics render as removable chips below the trigger.
 */
export function TopicPicker({
  value,
  onChange,
}: {
  value: PostTopic[];
  onChange: (topics: PostTopic[]) => void;
}) {
  const [open, setOpen] = useState(false);
  const [flat, setFlat] = useState<FlatTopic[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);

  // Fetch the tree lazily on first open (retried via `attempt`).
  useEffect(() => {
    if (!open || flat) return;
    let cancelled = false;
    api
      .get<{ data: Topic[] }>("/api/v1/topics/tree")
      .then((res) => {
        if (!cancelled) setFlat(flatten(res.data));
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, [open, flat, attempt]);

  function toggle(topic: PostTopic) {
    if (value.some((t) => t.id === topic.id)) {
      onChange(value.filter((t) => t.id !== topic.id));
    } else if (value.length < MAX_POST_TOPICS) {
      onChange([...value, topic]);
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <Popover
        open={open}
        onOpenChange={(next) => {
          setOpen(next);
          // Reopening after a failed load retries with a fresh state.
          if (next && failed) setFailed(false);
        }}
      >
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            className="h-11 justify-between gap-2"
          >
            <span className="flex items-center gap-2">
              <Hash className="size-4" aria-hidden />
              {value.length > 0
                ? `Topics (${value.length}/${MAX_POST_TOPICS})`
                : "Add topics"}
            </span>
            <ChevronDown
              className="size-4 text-muted-foreground"
              aria-hidden
            />
          </Button>
        </PopoverTrigger>
        <PopoverContent align="start" className="w-80 p-0">
          <p className="border-b px-3 py-2 text-xs text-muted-foreground">
            Pick up to {MAX_POST_TOPICS} topics for this post
          </p>
          <div className="max-h-64 overflow-y-auto p-1">
            {failed ? (
              <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                Couldn&apos;t load topics.{" "}
                <button
                  type="button"
                  className="font-medium text-foreground underline-offset-4 hover:underline"
                  onClick={() => {
                    setFailed(false);
                    setAttempt((count) => count + 1);
                  }}
                >
                  Try again
                </button>
              </p>
            ) : flat === null ? (
              <div className="flex flex-col gap-2 p-2">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Skeleton key={i} className="h-8 w-full" />
                ))}
              </div>
            ) : flat.length === 0 ? (
              <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                No topics available yet.
              </p>
            ) : (
              flat.map(({ topic, depth }) => {
                const checked = value.some((t) => t.id === topic.id);
                const disabled = !checked && value.length >= MAX_POST_TOPICS;
                const id = `composer-topic-${topic.id}`;
                return (
                  <label
                    key={topic.id}
                    htmlFor={id}
                    className={cn(
                      "flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60",
                      disabled && "cursor-not-allowed opacity-50",
                    )}
                    style={{ paddingLeft: `${8 + depth * 20}px` }}
                  >
                    <Checkbox
                      id={id}
                      checked={checked}
                      disabled={disabled}
                      onCheckedChange={() => toggle(topic)}
                    />
                    {topic.icon ? <span aria-hidden>{topic.icon}</span> : null}
                    <span className="truncate">{topic.name}</span>
                  </label>
                );
              })
            )}
          </div>
        </PopoverContent>
      </Popover>

      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {value.map((topic) => (
            <span
              key={topic.id}
              className="inline-flex items-center gap-1 rounded-full border bg-muted/40 py-0.5 pr-1 pl-2.5 text-xs"
            >
              {topic.icon ? <span aria-hidden>{topic.icon}</span> : null}
              {topic.name}
              <button
                type="button"
                aria-label={`Remove ${topic.name}`}
                className="flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                onClick={() =>
                  onChange(value.filter((t) => t.id !== topic.id))
                }
              >
                <X className="size-3" aria-hidden />
              </button>
            </span>
          ))}
        </div>
      ) : null}
    </div>
  );
}
