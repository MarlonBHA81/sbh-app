"use client";

import { Compass, FileText } from "lucide-react";
import Link from "next/link";
import { use, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { PostList, type PostListHandle } from "@/components/posts/post-list";
import { PullToRefresh } from "@/components/posts/pull-to-refresh";
import { TopicChip } from "@/components/topics/topic-chip";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Topic } from "@/lib/api/types";
import { withParam } from "@/lib/utils";

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

function TopicSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-start gap-4">
        <Skeleton className="size-16 rounded-2xl" />
        <div className="flex flex-1 flex-col gap-2 pt-1">
          <Skeleton className="h-6 w-40" />
          <Skeleton className="h-4 w-24" />
        </div>
        <Skeleton className="h-11 w-28 rounded-md" />
      </div>
      <Skeleton className="h-4 w-full max-w-sm" />
      <Skeleton className="h-40 w-full rounded-xl" />
    </div>
  );
}

export default function TopicPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = use(params);
  const { mutationCount } = useComposer();

  const [state, setState] = useState<{
    slug: string;
    topic: Topic | null;
    phase: "loading" | "loaded" | "error";
  }>({ slug, topic: null, phase: "loading" });
  const followBusy = useRef(false);
  const listRef = useRef<PostListHandle>(null);

  // Reset while rendering when navigating between topics (no effect needed).
  if (state.slug !== slug) {
    setState({ slug, topic: null, phase: "loading" });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Topic }>(`/api/v1/topics/${encodeURIComponent(slug)}`)
      .then((res) => {
        if (!cancelled) setState({ slug, topic: res.data, phase: "loaded" });
      })
      .catch(() => {
        if (!cancelled) setState({ slug, topic: null, phase: "error" });
      });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  const topic = state.topic;

  async function toggleFollow() {
    if (!topic || followBusy.current) return;
    followBusy.current = true;

    const wasFollowing = topic.is_following === true;
    const previous = topic;
    setState((prev) => ({
      ...prev,
      topic: {
        ...topic,
        is_following: !wasFollowing,
        followers_count: Math.max(
          0,
          topic.followers_count + (wasFollowing ? -1 : 1),
        ),
      },
    }));

    try {
      const path = `/api/v1/topics/${encodeURIComponent(slug)}/follow`;
      if (wasFollowing) await api.del(path);
      else await api.post(path);
    } catch (error) {
      setState((prev) => ({ ...prev, topic: previous }));
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update topic",
      );
    } finally {
      followBusy.current = false;
    }
  }

  if (state.phase === "loading") return <TopicSkeleton />;

  if (state.phase === "error" || !topic) {
    return (
      <EmptyState
        icon={Compass}
        title="Topic not found"
        description="This topic may have been removed or renamed."
      >
        <Button asChild variant="outline" className="mt-2 h-11">
          <Link href="/discover">Browse topics</Link>
        </Button>
      </EmptyState>
    );
  }

  const following = topic.is_following === true;
  const feedEndpoint = `/api/v1/feeds/topics/${encodeURIComponent(slug)}`;

  return (
    <div className="flex flex-col gap-4">
      <header className="flex items-start gap-4">
        <div
          className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-muted text-3xl"
          aria-hidden
        >
          {topic.icon ?? "🏷️"}
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-0.5 pt-0.5">
          <h1 className="truncate text-xl font-semibold tracking-tight">
            {topic.name}
          </h1>
          <p className="text-sm text-muted-foreground tabular-nums">
            {formatCount(topic.followers_count)}{" "}
            {topic.followers_count === 1 ? "follower" : "followers"}
          </p>
        </div>
        <Button
          variant={following ? "outline" : "default"}
          className="h-11 min-w-28"
          aria-pressed={following}
          onClick={() => void toggleFollow()}
        >
          {following ? "Following" : "Follow"}
        </Button>
      </header>

      {topic.description ? (
        <p className="text-sm leading-relaxed text-muted-foreground">
          {topic.description}
        </p>
      ) : null}

      {(topic.children ?? []).length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {(topic.children ?? []).map((child) => (
            <TopicChip key={child.slug} topic={child} />
          ))}
        </div>
      ) : null}

      <PullToRefresh
        onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
      >
        <PostList
          ref={listRef}
          buildUrl={(cursor) =>
            cursor ? withParam(feedEndpoint, "cursor", cursor) : feedEndpoint
          }
          refreshKey={`${slug}:${mutationCount}`}
          emptyState={
            <EmptyState
              icon={FileText}
              title="No posts yet"
              description={`Posts tagged with ${topic.name} will show up here.`}
            />
          }
        />
      </PullToRefresh>
    </div>
  );
}
