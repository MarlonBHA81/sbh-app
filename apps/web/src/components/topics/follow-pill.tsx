"use client";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/** Compact Follow/Following pill used in topic rows and lists. */
export function FollowPill({
  following,
  onToggle,
  topicName,
  className,
}: {
  following: boolean;
  onToggle: () => void;
  topicName: string;
  className?: string;
}) {
  return (
    <Button
      type="button"
      variant={following ? "outline" : "default"}
      size="sm"
      className={cn("h-8 w-24 shrink-0 rounded-full text-xs", className)}
      aria-pressed={following}
      aria-label={
        following ? `Unfollow ${topicName}` : `Follow ${topicName}`
      }
      onClick={onToggle}
    >
      {following ? "Following" : "Follow"}
    </Button>
  );
}
