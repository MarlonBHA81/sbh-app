"use client";

import {
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from "react";

import { AdSpotPlaceholder } from "@/components/ads/ad-spot-placeholder";
import { SponsorCard, useSponsorSlot } from "@/components/ads/sponsor-card";
import { PostCard } from "@/components/post-types/post-card";
import { SeenTracker } from "@/components/posts/seen-tracker";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Paginated, Post } from "@/lib/api/types";
import { useModerationStore } from "@/lib/stores/moderation-store";
import { useSettingsStore } from "@/lib/stores/settings-store";

/**
 * Insert an inline ad unit after every Nth feed item — a sponsor card when a
 * campaign fills the slot, otherwise a visible "ad spot" placeholder.
 */
const INLINE_SPONSOR_INTERVAL = 8;

/** In low data mode, ask for smaller pages (the backend may ignore it). */
function withListParams(url: string): string {
  if (!useSettingsStore.getState().lowData) return url;
  return `${url}${url.includes("?") ? "&" : "?"}limit=10`;
}

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

export interface PostListHandle {
  /**
   * Refetch page 1 and replace the list in place, keeping the current
   * content visible while the request is in flight (pull-to-refresh).
   */
  refresh: () => Promise<void>;
}

interface ListState {
  /** Identity of the currently loaded list (refreshKey + retry counter). */
  key: string;
  posts: Post[];
  nextCursor: string | null;
  phase: "loading" | "loaded" | "error";
  /** The error from the initial load (for custom error rendering). */
  error?: unknown;
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
  renderError,
  trackViews = true,
  showInlineSponsors = false,
  ref,
}: {
  buildUrl: (cursor: string | null) => string;
  emptyState: React.ReactNode;
  refreshKey?: unknown;
  renderItem?: (post: Post, helpers: PostListHelpers) => React.ReactNode;
  /**
   * Custom render for an initial-load failure (e.g. a 422 with a specific
   * message). `retry` refetches page 1. Falls back to the default error card.
   */
  renderError?: (error: unknown, retry: () => void) => React.ReactNode;
  /** Count items as viewed once seen (feeds). Off for own-post lists. */
  trackViews?: boolean;
  /** Render an inline sponsor slot every 15 items (home feed only). */
  showInlineSponsors?: boolean;
  ref?: React.Ref<PostListHandle>;
}) {
  const [retry, setRetry] = useState(0);
  const key = `${String(refreshKey)}#${retry}`;
  const hiddenProfileUlids = useModerationStore((s) => s.hiddenProfileUlids);
  const {
    slot: inlineSlot,
    status: inlineSlotStatus,
    dismiss: dismissInlineSlot,
  } = useSponsorSlot("feed_inline", { enabled: showInlineSponsors });

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
      .get<Paginated<Post>>(withListParams(buildUrlRef.current(null)))
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
      .catch((error: unknown) => {
        if (!cancelled) {
          setState({ key, posts: [], nextCursor: null, phase: "error", error });
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
        withListParams(buildUrlRef.current(nextCursor)),
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

  const refreshingRef = useRef(false);
  const refresh = useCallback(async () => {
    if (refreshingRef.current) return;
    refreshingRef.current = true;
    try {
      const res = await api.get<Paginated<Post>>(
        withListParams(buildUrlRef.current(null)),
      );
      setState((prev) =>
        prev.key !== key
          ? prev
          : {
              key,
              posts: res.data,
              nextCursor: res.meta.next_cursor,
              phase: "loaded",
            },
      );
    } catch {
      // Keep the current list on failure.
    } finally {
      refreshingRef.current = false;
    }
  }, [key]);

  useImperativeHandle(ref, () => ({ refresh }), [refresh]);

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
    if (renderError) {
      return (
        <>{renderError(state.error, () => setRetry((count) => count + 1))}</>
      );
    }
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

  // Live-remove posts from authors the viewer just blocked/muted (no refetch).
  const visiblePosts =
    hiddenProfileUlids.size === 0
      ? state.posts
      : state.posts.filter((p) => !hiddenProfileUlids.has(p.profile.ulid));

  if (visiblePosts.length === 0) {
    return <>{emptyState}</>;
  }

  return (
    <div className="flex flex-col gap-3">
      {visiblePosts.map((post, index) => {
        const item = renderItem ? (
          renderItem(post, helpers)
        ) : (
          <PostCard post={post} />
        );
        const isAdPosition =
          showInlineSponsors && (index + 1) % INLINE_SPONSOR_INTERVAL === 0;
        return (
          <div key={post.ulid} className="contents">
            {trackViews ? (
              <SeenTracker ulid={post.ulid}>{item}</SeenTracker>
            ) : (
              <div>{item}</div>
            )}
            {isAdPosition && inlineSlot ? (
              <SponsorCard
                slot={inlineSlot}
                variant="inline"
                onDismiss={dismissInlineSlot}
              />
            ) : isAdPosition && inlineSlotStatus === "empty" ? (
              <AdSpotPlaceholder variant="inline" />
            ) : null}
          </div>
        );
      })}
      {nextCursor ? (
        <>
          {loadingMore ? <PostSkeleton /> : null}
          <div ref={sentinelRef} aria-hidden className="h-px" />
        </>
      ) : null}
    </div>
  );
}
