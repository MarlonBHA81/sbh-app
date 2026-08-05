"use client";

import { Briefcase, ChevronDown, ChevronRight, MapPin, Trophy } from "lucide-react";
import Link from "next/link";
import { Fragment, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { FollowPill } from "@/components/topics/follow-pill";
import { TopicChip } from "@/components/topics/topic-chip";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Topic } from "@/lib/api/types";
import { useFeature } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

/** Immutably apply `fn` to the topic with `slug` anywhere in the tree. */
function mapTopics(
  topics: Topic[],
  slug: string,
  fn: (topic: Topic) => Topic,
): Topic[] {
  return topics.map((topic) =>
    topic.slug === slug
      ? fn(topic)
      : { ...topic, children: mapTopics(topic.children ?? [], slug, fn) },
  );
}

function TopicRow({
  topic,
  depth,
  expanded,
  onToggleExpand,
  onToggleFollow,
}: {
  topic: Topic;
  depth: number;
  expanded: boolean;
  onToggleExpand?: () => void;
  onToggleFollow: (topic: Topic) => void;
}) {
  const hasChildren = (topic.children ?? []).length > 0;

  return (
    <div
      className={cn(
        "flex min-h-14 items-center gap-2 py-2 pr-2 pl-3",
        depth > 0 && "bg-muted/30 pl-8",
      )}
    >
      <Link
        href={`/topics/${encodeURIComponent(topic.slug)}`}
        className="flex min-w-0 flex-1 items-center gap-3"
      >
        <span className={cn("text-xl", depth > 0 && "text-base")} aria-hidden>
          {topic.icon ?? "🏷️"}
        </span>
        <span className="flex min-w-0 flex-col">
          <span className="truncate text-sm font-medium">{topic.name}</span>
          <span className="text-xs text-muted-foreground tabular-nums">
            {formatCount(topic.followers_count)}{" "}
            {topic.followers_count === 1 ? "follower" : "followers"}
          </span>
        </span>
      </Link>
      <FollowPill
        following={topic.is_following === true}
        topicName={topic.name}
        onToggle={() => onToggleFollow(topic)}
      />
      {hasChildren && onToggleExpand ? (
        <button
          type="button"
          aria-expanded={expanded}
          aria-label={
            expanded
              ? `Hide ${topic.name} subtopics`
              : `Show ${topic.name} subtopics`
          }
          className="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:text-foreground"
          onClick={onToggleExpand}
        >
          <ChevronDown
            className={cn(
              "size-4 transition-transform motion-reduce:transition-none",
              expanded && "rotate-180",
            )}
            aria-hidden
          />
        </button>
      ) : (
        <span className="size-11 shrink-0" aria-hidden />
      )}
    </div>
  );
}

function TopicListSkeleton() {
  return (
    <div className="divide-y rounded-xl border">
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="flex min-h-14 items-center gap-3 px-3 py-2">
          <Skeleton className="size-8 rounded-lg" />
          <div className="flex flex-1 flex-col gap-1.5">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-3 w-20" />
          </div>
          <Skeleton className="h-8 w-24 rounded-full" />
        </div>
      ))}
    </div>
  );
}

export function DiscoverView() {
  const [tree, setTree] = useState<Topic[]>([]);
  const [myTopics, setMyTopics] = useState<Topic[]>([]);
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">(
    "loading",
  );
  const [retry, setRetry] = useState(0);
  const [expanded, setExpanded] = useState<ReadonlySet<string>>(new Set());
  const busySlugs = useRef(new Set<string>());
  const gamificationOn = useFeature("gamification");

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      api.get<{ data: Topic[] }>("/api/v1/topics/tree"),
      // A failing "your topics" call shouldn't take the page down.
      api
        .get<{ data: Topic[] }>("/api/v1/me/topics")
        .catch(() => ({ data: [] as Topic[] })),
    ])
      .then(([treeRes, mineRes]) => {
        if (cancelled) return;
        setTree(treeRes.data);
        setMyTopics(mineRes.data);
        setPhase("loaded");
      })
      .catch(() => {
        if (!cancelled) setPhase("error");
      });
    return () => {
      cancelled = true;
    };
  }, [retry]);

  function toggleExpand(slug: string) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(slug)) next.delete(slug);
      else next.add(slug);
      return next;
    });
  }

  async function toggleFollow(topic: Topic) {
    if (busySlugs.current.has(topic.slug)) return;
    busySlugs.current.add(topic.slug);

    const wasFollowing = topic.is_following === true;
    const patch = (t: Topic): Topic => ({
      ...t,
      is_following: !wasFollowing,
      followers_count: Math.max(
        0,
        t.followers_count + (wasFollowing ? -1 : 1),
      ),
    });
    const revert = (t: Topic): Topic => ({
      ...t,
      is_following: wasFollowing,
      followers_count: topic.followers_count,
    });

    // Optimistic: flip the tree node and the "Your topics" section.
    setTree((prev) => mapTopics(prev, topic.slug, patch));
    setMyTopics((prev) =>
      wasFollowing
        ? prev.filter((t) => t.slug !== topic.slug)
        : prev.some((t) => t.slug === topic.slug)
          ? prev
          : [...prev, patch(topic)],
    );

    try {
      const path = `/api/v1/topics/${encodeURIComponent(topic.slug)}/follow`;
      if (wasFollowing) await api.del(path);
      else await api.post(path);
    } catch (error) {
      setTree((prev) => mapTopics(prev, topic.slug, revert));
      setMyTopics((prev) =>
        wasFollowing
          ? prev.some((t) => t.slug === topic.slug)
            ? prev
            : [...prev, topic]
          : prev.filter((t) => t.slug !== topic.slug),
      );
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update topic",
      );
    } finally {
      busySlugs.current.delete(topic.slug);
    }
  }

  if (phase === "loading") {
    return <TopicListSkeleton />;
  }

  if (phase === "error") {
    return (
      <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
        Couldn&apos;t load topics.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => {
            setPhase("loading");
            setRetry((count) => count + 1);
          }}
        >
          Try again
        </button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {gamificationOn ? (
        <Link
          href="/leaderboard"
          className="sbh-lift flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-accent/40 p-3 hover:bg-accent/60"
        >
          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-gold-text">
            <Trophy className="size-5" aria-hidden />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-semibold">
              Community leaderboard
            </span>
            <span className="block text-xs text-muted-foreground">
              See who&apos;s earning the most XP this week.
            </span>
          </span>
          <ChevronRight
            className="size-5 shrink-0 text-muted-foreground"
            aria-hidden
          />
        </Link>
      ) : null}

      <Link
        href="/business"
        className="sbh-lift flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-accent/40 p-3 hover:bg-accent/60"
      >
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-indigo-600 dark:text-indigo-400">
          <Briefcase className="size-5" aria-hidden />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-sm font-semibold">Business hub</span>
          <span className="block text-xs text-muted-foreground">
            Browse the directory, find events, and get matched.
          </span>
        </span>
        <ChevronRight
          className="size-5 shrink-0 text-muted-foreground"
          aria-hidden
        />
      </Link>

      <Link
        href="/map"
        className="sbh-lift flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-accent/40 p-3 hover:bg-accent/60"
      >
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-sage-text">
          <MapPin className="size-5" aria-hidden />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-sm font-semibold">Who&apos;s nearby</span>
          <span className="block text-xs text-muted-foreground">
            Explore people and businesses around you on the map.
          </span>
        </span>
        <ChevronRight
          className="size-5 shrink-0 text-muted-foreground"
          aria-hidden
        />
      </Link>

      {myTopics.length > 0 ? (
        <section className="flex flex-col gap-2" aria-label="Your topics">
          <h2 className="text-sm font-semibold text-muted-foreground">
            Your topics
          </h2>
          <div className="flex flex-wrap gap-1.5">
            {myTopics.map((topic) => (
              <TopicChip key={topic.slug} topic={topic} />
            ))}
          </div>
        </section>
      ) : null}

      <section className="flex flex-col gap-3" aria-label="Browse topics">
        <h2 className="text-lg font-semibold tracking-tight">Browse topics</h2>
        <div className="divide-y overflow-hidden rounded-xl border">
          {tree.map((root) => (
            <Fragment key={root.slug}>
              <TopicRow
                topic={root}
                depth={0}
                expanded={expanded.has(root.slug)}
                onToggleExpand={() => toggleExpand(root.slug)}
                onToggleFollow={(topic) => void toggleFollow(topic)}
              />
              {expanded.has(root.slug)
                ? (root.children ?? []).map((child) => (
                    <TopicRow
                      key={child.slug}
                      topic={child}
                      depth={1}
                      expanded={false}
                      onToggleFollow={(topic) => void toggleFollow(topic)}
                    />
                  ))
                : null}
            </Fragment>
          ))}
        </div>
      </section>
    </div>
  );
}
