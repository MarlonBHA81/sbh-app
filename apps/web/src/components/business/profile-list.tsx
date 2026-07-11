"use client";

import {
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from "react";

import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Paginated, Profile } from "@/lib/api/types";
import { useModerationStore } from "@/lib/stores/moderation-store";

export function ProfileRowSkeleton() {
  return (
    <div className="flex items-center gap-3 rounded-xl border p-4">
      <Skeleton className="size-12 shrink-0 rounded-full" />
      <div className="flex flex-1 flex-col gap-1.5">
        <Skeleton className="h-4 w-40" />
        <Skeleton className="h-3 w-28" />
        <Skeleton className="h-3 w-24" />
      </div>
      <Skeleton className="h-8 w-24 rounded-full" />
    </div>
  );
}

export interface ProfileListHelpers {
  remove: (ulid: string) => void;
  replace: (profile: Profile) => void;
}

export interface ProfileListHandle {
  /** Refetch page 1 in place (pull-to-refresh). */
  refresh: () => Promise<void>;
}

interface ListState {
  key: string;
  profiles: Profile[];
  nextCursor: string | null;
  phase: "loading" | "loaded" | "error";
  error?: unknown;
}

/**
 * Cursor-paginated infinite profile list with an IntersectionObserver
 * sentinel — the profile counterpart to PostList. `buildUrl` receives the
 * cursor (null for the first page); change `refreshKey` to refetch from the top.
 */
export function ProfileList({
  buildUrl,
  emptyState,
  refreshKey,
  renderItem,
  renderError,
  ref,
}: {
  buildUrl: (cursor: string | null) => string;
  emptyState: React.ReactNode;
  refreshKey?: unknown;
  renderItem: (profile: Profile, helpers: ProfileListHelpers) => React.ReactNode;
  renderError?: (error: unknown, retry: () => void) => React.ReactNode;
  ref?: React.Ref<ProfileListHandle>;
}) {
  const [retry, setRetry] = useState(0);
  const key = `${String(refreshKey)}#${retry}`;
  const hiddenProfileUlids = useModerationStore((s) => s.hiddenProfileUlids);

  const [state, setState] = useState<ListState>({
    key,
    profiles: [],
    nextCursor: null,
    phase: "loading",
  });
  const [loadingMore, setLoadingMore] = useState(false);
  const sentinelRef = useRef<HTMLDivElement | null>(null);

  const buildUrlRef = useRef(buildUrl);
  useEffect(() => {
    buildUrlRef.current = buildUrl;
  });

  if (state.key !== key) {
    setState({ key, profiles: [], nextCursor: null, phase: "loading" });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Profile>>(buildUrlRef.current(null))
      .then((res) => {
        if (!cancelled) {
          setState({
            key,
            profiles: res.data,
            nextCursor: res.meta.next_cursor,
            phase: "loaded",
          });
        }
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setState({
            key,
            profiles: [],
            nextCursor: null,
            phase: "error",
            error,
          });
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
      const res = await api.get<Paginated<Profile>>(
        buildUrlRef.current(nextCursor),
      );
      setState((prev) => {
        if (prev.key !== key) return prev;
        const seen = new Set(prev.profiles.map((p) => p.ulid));
        return {
          ...prev,
          profiles: [
            ...prev.profiles,
            ...res.data.filter((p) => !seen.has(p.ulid)),
          ],
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
      const res = await api.get<Paginated<Profile>>(buildUrlRef.current(null));
      setState((prev) =>
        prev.key !== key
          ? prev
          : {
              key,
              profiles: res.data,
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

  const helpers: ProfileListHelpers = {
    remove: (ulid) =>
      setState((prev) => ({
        ...prev,
        profiles: prev.profiles.filter((p) => p.ulid !== ulid),
      })),
    replace: (profile) =>
      setState((prev) => ({
        ...prev,
        profiles: prev.profiles.map((p) =>
          p.ulid === profile.ulid ? profile : p,
        ),
      })),
  };

  if (state.phase === "loading") {
    return (
      <div className="flex flex-col gap-3">
        <ProfileRowSkeleton />
        <ProfileRowSkeleton />
        <ProfileRowSkeleton />
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
        Couldn&apos;t load results.{" "}
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

  const visible =
    hiddenProfileUlids.size === 0
      ? state.profiles
      : state.profiles.filter((p) => !hiddenProfileUlids.has(p.ulid));

  if (visible.length === 0) {
    return <>{emptyState}</>;
  }

  return (
    <div className="flex flex-col gap-3">
      {visible.map((profile) => (
        <div key={profile.ulid}>{renderItem(profile, helpers)}</div>
      ))}
      {nextCursor ? (
        <>
          {loadingMore ? <ProfileRowSkeleton /> : null}
          <div ref={sentinelRef} aria-hidden className="h-px" />
        </>
      ) : null}
    </div>
  );
}
