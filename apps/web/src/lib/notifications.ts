import {
  AtSign,
  Heart,
  MessageCircle,
  Quote,
  Repeat2,
  Trophy,
  UserPlus,
  type LucideIcon,
} from "lucide-react";

import type { AppNotification, NotificationType } from "@/lib/api/types";

/**
 * Accepts both notification wire shapes and returns the canonical one:
 * the REST list returns FLAT rows ({id, type, actor, post_ulid, ...}),
 * while the typed shape (and broadcast payloads) nest those under `data`.
 */
export function normalizeNotification(raw: unknown): AppNotification | null {
  if (!raw || typeof raw !== "object") return null;
  const n = raw as Record<string, unknown>;
  if (typeof n.id !== "string" || typeof n.type !== "string") return null;
  const source = (
    n.data && typeof n.data === "object" ? n.data : n
  ) as AppNotification["data"];
  return {
    id: n.id,
    type: n.type as NotificationType,
    read_at: typeof n.read_at === "string" ? n.read_at : null,
    created_at:
      typeof n.created_at === "string" ? n.created_at : new Date().toISOString(),
    data: {
      actor: source.actor,
      post_ulid: source.post_ulid,
      comment_ulid: source.comment_ulid,
      preview: source.preview,
      rank: source.rank,
    },
  };
}


/** Maps a notification type to its key under the `notifications` namespace. */
const ACTION_KEY: Record<NotificationType, string> = {
  new_follower: "newFollower",
  follow_requested: "followRequested",
  follow_accepted: "followAccepted",
  post_liked: "postLiked",
  post_commented: "postCommented",
  comment_replied: "commentReplied",
  comment_liked: "commentLiked",
  mentioned: "mentioned",
  post_reposted: "postReposted",
  post_quoted: "postQuoted",
  rank_unlocked: "rankUnlocked",
};

/** A translator function, e.g. from `useTranslations("notifications")`. */
type Translate = (key: string, values?: Record<string, string>) => string;

const ICONS: Record<NotificationType, LucideIcon> = {
  new_follower: UserPlus,
  follow_requested: UserPlus,
  follow_accepted: UserPlus,
  post_liked: Heart,
  post_commented: MessageCircle,
  comment_replied: MessageCircle,
  comment_liked: Heart,
  mentioned: AtSign,
  post_reposted: Repeat2,
  post_quoted: Quote,
  rank_unlocked: Trophy,
};

/** Trailing action text, e.g. "liked your post". Localized via `t`. */
export function notificationAction(
  notification: AppNotification,
  t: Translate,
): string {
  if (notification.type === "rank_unlocked") {
    const name = notification.data.rank?.name;
    return name
      ? t("rankUnlockedNamed", { rank: name })
      : t("rankUnlocked");
  }
  const key = ACTION_KEY[notification.type];
  return key ? t(key) : t("fallback");
}

export function notificationIcon(notification: AppNotification): LucideIcon {
  return ICONS[notification.type] ?? Heart;
}

/** Where tapping a notification should navigate. */
export function notificationHref(notification: AppNotification): string {
  const { data } = notification;
  if (notification.type === "rank_unlocked") return "/leaderboard";
  if (data.post_ulid) return `/p/${data.post_ulid}`;
  if (data.actor) return `/${data.actor.handle}`;
  return "/notifications";
}
