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
