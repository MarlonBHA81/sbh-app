"use client";

import { Bell, BellRing, X } from "lucide-react";
import Link from "next/link";
import { createElement, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useClientValue } from "@/hooks/use-client-value";
import * as api from "@/lib/api/client";
import type { AppNotification, Paginated } from "@/lib/api/types";
import {
  notificationAction,
  notificationHref,
  notificationIcon,
} from "@/lib/notifications";
import { isPushSupported, pushPermission, subscribeToPush } from "@/lib/push";
import { useNotificationsStore } from "@/lib/stores/notifications-store";
import { relativeTime } from "@/lib/time";
import { cn } from "@/lib/utils";

const PUSH_BANNER_KEY = "sbh.pushBannerDismissed";

function bannerEligible(): boolean {
  if (!isPushSupported() || pushPermission() !== "default") return false;
  try {
    return window.localStorage.getItem(PUSH_BANNER_KEY) !== "1";
  } catch {
    return true;
  }
}

function PushBanner() {
  const eligible = useClientValue(bannerEligible, false);
  const [dismissed, setDismissed] = useState(false);
  const [busy, setBusy] = useState(false);

  function dismiss() {
    setDismissed(true);
    try {
      window.localStorage.setItem(PUSH_BANNER_KEY, "1");
    } catch {
      // ignore storage errors
    }
  }

  async function enable() {
    setBusy(true);
    const result = await subscribeToPush();
    setBusy(false);
    if (result === "subscribed") {
      toast.success("Push notifications enabled");
      dismiss();
    } else if (result === "denied") {
      toast.error("Notifications are blocked in your browser settings");
      dismiss();
    } else if (result === "disabled" || result === "unsupported") {
      dismiss();
    } else {
      toast.error("Couldn't enable push notifications");
    }
  }

  if (!eligible || dismissed) return null;

  return (
    <div className="flex items-center gap-3 rounded-xl border bg-accent/40 p-3">
      <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-background">
        <BellRing className="size-4 text-primary" aria-hidden />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium">Turn on push notifications</p>
        <p className="text-xs text-muted-foreground">
          Get alerts for follows, mentions and activity — even when SBH is
          closed.
        </p>
      </div>
      <Button
        type="button"
        size="sm"
        className="h-9 shrink-0"
        disabled={busy}
        onClick={() => void enable()}
      >
        {busy ? "Enabling…" : "Enable"}
      </Button>
      <button
        type="button"
        aria-label="Dismiss"
        onClick={dismiss}
        className="shrink-0 rounded-md p-1 text-muted-foreground hover:text-foreground"
      >
        <X className="size-4" aria-hidden />
      </button>
    </div>
  );
}

function NotificationRow({
  notification,
  onRead,
}: {
  notification: AppNotification;
  onRead: (id: string) => void;
}) {
  const href = notificationHref(notification);
  const unread = notification.read_at == null;
  const { actor } = notification.data;

  return (
    <Link
      href={href}
      onClick={() => {
        if (unread) onRead(notification.id);
      }}
      className={cn(
        "flex items-start gap-3 rounded-xl border p-3 transition-colors hover:bg-accent/40",
        unread && "border-primary/30 bg-accent/30",
      )}
    >
      <div className="relative shrink-0">
        <ProfileAvatar profile={actor} className="size-10" />
        <span className="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-background">
          {createElement(notificationIcon(notification), {
            className: "size-3.5 text-muted-foreground",
            "aria-hidden": true,
          })}
        </span>
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm leading-snug">
          <span className="font-semibold">{actor.name}</span>{" "}
          <span className="text-muted-foreground">
            {notificationAction(notification)}
          </span>
        </p>
        {notification.data.preview ? (
          <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
            {notification.data.preview}
          </p>
        ) : null}
        <time
          dateTime={notification.created_at}
          className="mt-0.5 block text-xs text-muted-foreground"
        >
          {relativeTime(notification.created_at)}
        </time>
      </div>
      {unread ? (
        <span
          className="mt-1 size-2.5 shrink-0 rounded-full bg-primary"
          aria-label="Unread"
        />
      ) : null}
    </Link>
  );
}

interface ViewState {
  key: number;
  items: AppNotification[];
  cursor: string | null;
  phase: "loading" | "loaded" | "error";
}

export function NotificationsView() {
  const clearUnread = useNotificationsStore((s) => s.clearUnread);

  const [retry, setRetry] = useState(0);
  const [state, setState] = useState<ViewState>({
    key: 0,
    items: [],
    cursor: null,
    phase: "loading",
  });
  const [loadingMore, setLoadingMore] = useState(false);
  // Ids already shown, so live notifications aren't inserted twice.
  const seenIds = useRef<Set<string>>(new Set());

  // Reset while rendering when a retry is requested.
  if (state.key !== retry) {
    setState({ key: retry, items: [], cursor: null, phase: "loading" });
  }

  // Visiting the page clears the unread badge.
  useEffect(() => {
    clearUnread();
  }, [clearUnread]);

  useEffect(() => {
    let cancelled = false;
    seenIds.current = new Set();
    api
      .get<Paginated<AppNotification>>("/api/v1/notifications")
      .then((res) => {
        if (cancelled) return;
        // Merge any notifications that already arrived live before mount.
        const live = useNotificationsStore.getState().live;
        const merged = [...res.data];
        merged.forEach((n) => seenIds.current.add(n.id));
        const extras = live.filter((n) => !seenIds.current.has(n.id));
        extras.forEach((n) => seenIds.current.add(n.id));
        setState({
          key: retry,
          items: [...extras, ...merged],
          cursor: res.meta.next_cursor,
          phase: "loaded",
        });
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key: retry, items: [], cursor: null, phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [retry]);

  // Prepend live notifications as they arrive (store callback — allowed).
  useEffect(() => {
    const unsubscribe = useNotificationsStore.subscribe((s) => {
      const fresh = s.live.filter((n) => !seenIds.current.has(n.id));
      if (fresh.length === 0) return;
      fresh.forEach((n) => seenIds.current.add(n.id));
      setState((prev) =>
        prev.phase === "loaded"
          ? { ...prev, items: [...fresh, ...prev.items] }
          : prev,
      );
    });
    return unsubscribe;
  }, []);

  async function loadMore() {
    if (!state.cursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<AppNotification>>(
        `/api/v1/notifications?cursor=${encodeURIComponent(state.cursor)}`,
      );
      setState((prev) => {
        const next = res.data.filter((n) => !seenIds.current.has(n.id));
        next.forEach((n) => seenIds.current.add(n.id));
        return {
          ...prev,
          items: [...prev.items, ...next],
          cursor: res.meta.next_cursor,
        };
      });
    } catch {
      // Keep the current list.
    } finally {
      setLoadingMore(false);
    }
  }

  function markRead(id: string) {
    setState((prev) => ({
      ...prev,
      items: prev.items.map((n) =>
        n.id === id && n.read_at == null
          ? { ...n, read_at: new Date().toISOString() }
          : n,
      ),
    }));
    api.post(`/api/v1/notifications/${id}/read`).catch(() => {
      // Best-effort; unread state self-heals on next fetch.
    });
  }

  async function markAllRead() {
    const now = new Date().toISOString();
    setState((prev) => ({
      ...prev,
      items: prev.items.map((n) =>
        n.read_at == null ? { ...n, read_at: now } : n,
      ),
    }));
    clearUnread();
    try {
      await api.post("/api/v1/notifications/read-all");
    } catch {
      toast.error("Couldn't mark all as read");
    }
  }

  const hasUnread = state.items.some((n) => n.read_at == null);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold tracking-tight">Notifications</h1>
        {hasUnread ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-9"
            onClick={() => void markAllRead()}
          >
            Mark all read
          </Button>
        ) : null}
      </div>

      <PushBanner />

      {state.phase === "loading" ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex gap-3 rounded-xl border p-3">
              <Skeleton className="size-10 rounded-full" />
              <div className="flex flex-1 flex-col gap-1.5">
                <Skeleton className="h-3 w-40" />
                <Skeleton className="h-3 w-24" />
              </div>
            </div>
          ))}
        </div>
      ) : null}

      {state.phase === "error" ? (
        <p className="py-6 text-center text-sm text-muted-foreground">
          Couldn&apos;t load notifications.{" "}
          <button
            type="button"
            className="font-medium text-foreground underline-offset-4 hover:underline"
            onClick={() => setRetry((c) => c + 1)}
          >
            Try again
          </button>
        </p>
      ) : null}

      {state.phase === "loaded" && state.items.length === 0 ? (
        <EmptyState
          icon={Bell}
          title="No notifications yet"
          description="Follows, mentions and activity on your posts will show up here."
        />
      ) : null}

      {state.phase === "loaded" && state.items.length > 0 ? (
        <div className="flex flex-col gap-2">
          {state.items.map((notification) => (
            <NotificationRow
              key={notification.id}
              notification={notification}
              onRead={markRead}
            />
          ))}
          {state.cursor ? (
            <button
              type="button"
              onClick={() => void loadMore()}
              disabled={loadingMore}
              className="mt-1 self-center text-sm font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
            >
              {loadingMore ? "Loading…" : "Load more"}
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
