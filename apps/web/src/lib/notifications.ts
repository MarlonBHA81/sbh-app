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

const ACTION_TEXT: Record<NotificationType, string> = {
  new_follower: "followed you",
  follow_requested: "requested to follow you",
  follow_accepted: "accepted your follow request",
  post_liked: "liked your post",
  post_commented: "commented on your post",
  comment_replied: "replied to your comment",
  comment_liked: "liked your comment",
  mentioned: "mentioned you",
  post_reposted: "reposted your post",
  post_quoted: "quoted your post",
  rank_unlocked: "unlocked a new rank",
};

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

/** Trailing action text, e.g. "liked your post". */
export function notificationAction(notification: AppNotification): string {
  if (notification.type === "rank_unlocked") {
    const name = notification.data.rank?.name;
    return name ? `You unlocked the ${name} rank` : "You unlocked a new rank";
  }
  return ACTION_TEXT[notification.type] ?? "sent you a notification";
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
