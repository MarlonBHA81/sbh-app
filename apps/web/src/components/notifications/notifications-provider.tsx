"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { AppNotification } from "@/lib/api/types";
import { useEchoPrivate } from "@/lib/echo";
import { notificationAction, notificationHref } from "@/lib/notifications";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useNotificationsStore } from "@/lib/stores/notifications-store";

/** Best-effort coercion of an Echo notification broadcast into AppNotification. */
function toAppNotification(payload: unknown): AppNotification | null {
  if (!payload || typeof payload !== "object") return null;
  const p = payload as Record<string, unknown>;
  const type = p.type as AppNotification["type"] | undefined;
  const source = (p.data ?? p) as Record<string, unknown>;
  const actor = source.actor as AppNotification["data"]["actor"] | undefined;
  if (!type || !actor) return null;

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
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const setUnreadCount = useNotificationsStore((s) => s.setUnreadCount);
  const pushLive = useNotificationsStore((s) => s.pushLive);

  // Sync the unread badge whenever the active profile changes.
  useEffect(() => {
    if (!activeUlid) return;
    let cancelled = false;
    api
      .get<{ count: number }>("/api/v1/notifications/unread-count")
      .then((res) => {
        if (!cancelled) setUnreadCount(res.count ?? 0);
      })
      .catch(() => {
        // Non-fatal: leave the badge as-is.
      });
    return () => {
      cancelled = true;
    };
  }, [activeUlid, setUnreadCount]);

  useEchoPrivate(
    activeUlid ? `profile.${activeUlid}` : null,
    (payload) => {
      const notification = toAppNotification(payload);
      if (!notification) return;
      pushLive(notification);

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
                {notification.data.actor.name}
              </span>{" "}
              {notificationAction(notification)}
            </span>
          </button>
        ),
        { duration: 6000 },
      );
    },
  );

  return null;
}
