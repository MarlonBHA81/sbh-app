"use client";

import { useCallback, useEffect, useRef, useState } from "react";

import { PostCard } from "@/components/post-types/post-card";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Paginated, Post } from "@/lib/api/types";

export function PostSkeleton() {
  return (
    <div className="flex flex-col gap-3 rounded-xl border p-4">
      <div className="flex items-center gap-3">
        <Skeleton className="size-10 rounded-full" />
        <div className="flex flex-col gap-1.5">
          <Skeleton className="h-4 w-32" />
          <Skeleton className="h-3 w-24" />
        </div>
      </div>
      <Skeleton className="h-4 w-full" />
      <Skeleton className="h-4 w-3/4" />
    </div>
  );
}

export interface PostListHelpers {
  remove: (ulid: string) => void;
  replace: (post: Post) => void;
}

interface ListState {
  /** Identity of the currently loaded list (refreshKey + retry counter). */
  key: string;
  posts: Post[];
  nextCursor: string | null;
  phase: "loading" | "loaded" | "error";
}

/**
 * Cursor-paginated infinite post list with an IntersectionObserver sentinel.
 * `buildUrl` receives the cursor (null for the first page) and returns the
 * request URL. Change `refreshKey` to refetch from the top (e.g. after the
 * composer publishes).
 */
export function PostList({
  buildUrl,
  emptyState,
  refreshKey,
  renderItem,
}: {
  buildUrl: (cursor: string | null) => string;
  emptyState: React.ReactNode;
  refreshKey?: unknown;
  renderItem?: (post: Post, helpers: PostListHelpers) => React.ReactNode;
}) {
  const [retry, setRetry] = useState(0);
  const key = `${String(refreshKey)}#${retry}`;

  const [state, setState] = useState<ListState>({
    key,
    posts: [],
    nextCursor: null,
    phase: "loading",
  });
  const [loadingMore, setLoadingMore] = useState(false);
  const sentinelRef = useRef<HTMLDivElement | null>(null);

  // buildUrl is typically an inline arrow — keep the latest without making
  // it an effect dependency.
  const buildUrlRef = useRef(buildUrl);
  useEffect(() => {
    buildUrlRef.current = buildUrl;
  });

  // Reset while rendering when the list identity changes (no effect needed).
  if (state.key !== key) {
    setState({ key, posts: [], nextCursor: null, phase: "loading" });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Post>>(buildUrlRef.current(null))
      .then((res) => {
        if (!cancelled) {
          setState({
            key,
            posts: res.data,
            nextCursor: res.meta.next_cursor,
            phase: "loaded",
          });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key, posts: [], nextCursor: null, phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [key]);

  const nextCursor = state.phase === "loaded" ? state.nextCursor : null;

  const loadMore = useCallback(async () => {
    if (!nextCursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<Post>>(
        buildUrlRef.current(nextCursor),
      );
      setState((prev) => {
        if (prev.key !== key) return prev;
        const seen = new Set(prev.posts.map((p) => p.ulid));
        return {
          ...prev,
          posts: [...prev.posts, ...res.data.filter((p) => !seen.has(p.ulid))],
          nextCursor: res.meta.next_cursor,
        };
      });
    } catch {
      // Keep the current list; the sentinel retries when re-intersecting.
    } finally {
      setLoadingMore(false);
    }
  }, [nextCursor, loadingMore, key]);

  useEffect(() => {
    const node = sentinelRef.current;
    if (!node || !nextCursor) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) void loadMore();
      },
      { rootMargin: "400px" },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [nextCursor, loadMore]);

  const helpers: PostListHelpers = {
    remove: (ulid) =>
      setState((prev) => ({
        ...prev,
        posts: prev.posts.filter((p) => p.ulid !== ulid),
      })),
    replace: (post) =>
      setState((prev) => ({
        ...prev,
        posts: prev.posts.map((p) => (p.ulid === post.ulid ? post : p)),
      })),
  };

  if (state.phase === "loading") {
    return (
      <div className="flex flex-col gap-3">
        <PostSkeleton />
        <PostSkeleton />
        <PostSkeleton />
      </div>
    );
  }

  if (state.phase === "error") {
    return (
      <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
        Couldn&apos;t load posts.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => setRetry((count) => count + 1)}
        >
          Try again
        </button>
      </div>
    );
  }

  if (state.posts.length === 0) {
    return <>{emptyState}</>;
  }

  return (
    <div className="flex flex-col gap-3">
      {state.posts.map((post) =>
        renderItem ? (
          <div key={post.ulid}>{renderItem(post, helpers)}</div>
        ) : (
          <PostCard key={post.ulid} post={post} />
        ),
      )}
      {nextCursor ? (
        <>
          {loadingMore ? <PostSkeleton /> : null}
          <div ref={sentinelRef} aria-hidden className="h-px" />
        </>
      ) : null}
    </div>
  );
}
