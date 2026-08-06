"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useCallback, useEffect } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { AppNotification, RankSummary } from "@/lib/api/types";
import { useEchoPrivate, usePollingFallback } from "@/lib/echo";
import { notificationAction, notificationHref } from "@/lib/notifications";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useGamificationStore } from "@/lib/stores/gamification-store";
import { useNotificationsStore } from "@/lib/stores/notifications-store";

/** Coerce a loosely-typed rank payload into a RankSummary, if valid. */
function toRankSummary(value: unknown): RankSummary | undefined {
  if (!value || typeof value !== "object") return undefined;
  const r = value as Record<string, unknown>;
  if (typeof r.key !== "string" || typeof r.name !== "string") return undefined;
  return {
    key: r.key,
    name: r.name,
    icon: typeof r.icon === "string" ? r.icon : null,
  };
}

/** Best-effort coercion of an Echo notification broadcast into AppNotification. */
function toAppNotification(payload: unknown): AppNotification | null {
  if (!payload || typeof payload !== "object") return null;
  const p = payload as Record<string, unknown>;
  const type = p.type as AppNotification["type"] | undefined;
  const source = (p.data ?? p) as Record<string, unknown>;
  const actor = source.actor as AppNotification["data"]["actor"] | undefined;
  const rank = toRankSummary(source.rank);
  // Every notification carries either an actor or (for rank_unlocked) a rank.
  if (!type || (!actor && !rank)) return null;

  return {
    id:
      typeof p.id === "string"
        ? p.id
        : String(p.id ?? `${type}-${Date.now()}-${Math.random()}`),
    type,
    data: {
      actor,
      post_ulid:
        typeof source.post_ulid === "string" ? source.post_ulid : undefined,
      comment_ulid:
        typeof source.comment_ulid === "string"
          ? source.comment_ulid
          : undefined,
      preview: typeof source.preview === "string" ? source.preview : undefined,
      rank,
    },
    read_at: typeof p.read_at === "string" ? p.read_at : null,
    created_at:
      typeof p.created_at === "string" ? p.created_at : new Date().toISOString(),
  };
}

/**
 * App-wide realtime notifications driver. Fetches the unread count and
 * subscribes to the active profile's private channel; re-subscribes when the
 * active profile switches. New notifications bump the badge and raise a toast.
 * Renders nothing.
 */
export function NotificationsProvider() {
  const router = useRouter();
  const t = useTranslations("notifications");
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const setUnreadCount = useNotificationsStore((s) => s.setUnreadCount);
  const pushLive = useNotificationsStore((s) => s.pushLive);
  const celebrateRank = useGamificationStore((s) => s.celebrateRank);

  // Refresh the unread badge from the server.
  const refreshUnread = useCallback(() => {
    if (!activeUlid) return;
    api
      .get<{ count: number }>("/api/v1/notifications/unread-count")
      .then((res) => setUnreadCount(res.count ?? 0))
      .catch(() => {
        // Non-fatal: leave the badge as-is.
      });
  }, [activeUlid, setUnreadCount]);

  // Sync the unread badge whenever the active profile changes.
  useEffect(() => {
    refreshUnread();
  }, [refreshUnread]);

  // When Reverb isn't configured, live pushes never arrive — poll the badge so
  // it doesn't sit stale.
  usePollingFallback(refreshUnread, { enabled: Boolean(activeUlid) });

  useEchoPrivate(
    activeUlid ? `profile.${activeUlid}` : null,
    (payload) => {
      const notification = toAppNotification(payload);
      if (!notification) return;
      pushLive(notification);

      // Rank unlocks get a full-screen celebration instead of a plain toast.
      if (notification.type === "rank_unlocked") {
        if (notification.data.rank) celebrateRank(notification.data.rank);
        return;
      }

      const href = notificationHref(notification);
      toast.custom(
        (id) => (
          <button
            type="button"
            onClick={() => {
              toast.dismiss(id);
              router.push(href);
            }}
            className="flex w-full items-center gap-2 rounded-md border bg-popover px-3 py-2.5 text-left text-sm text-popover-foreground shadow-md"
          >
            <span className="min-w-0 flex-1 truncate">
              <span className="font-semibold">
                {notification.data.actor?.name}
              </span>{" "}
              {notificationAction(notification, t)}
            </span>
          </button>
        ),
        { duration: 6000 },
      );
    },
  );

  return null;
}
