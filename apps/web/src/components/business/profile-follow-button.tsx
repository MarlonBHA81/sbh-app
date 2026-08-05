"use client";

import { useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/**
 * Compact optimistic Follow/Following/Requested button for a profile, mirroring
 * the follow logic on the profile page. `onChange` lets a parent list keep its
 * copy of the profile in sync. Stops click propagation so it can live inside a
 * card that navigates on click.
 */
export function ProfileFollowButton({
  profile,
  onChange,
  className,
}: {
  profile: Profile;
  onChange?: (next: Profile) => void;
  className?: string;
}) {
  const [busy, setBusy] = useState(false);

  if (profile.relationship === "self" || profile.relationship === "blocked") {
    return null;
  }

  const following = profile.relationship === "following";
  const pending = profile.relationship === "pending";

  let label = "Follow";
  let variant: "default" | "outline" = "default";
  if (following) {
    label = "Following";
    variant = "outline";
  } else if (pending) {
    label = "Requested";
    variant = "outline";
  }

  async function toggle() {
    if (busy) return;
    setBusy(true);

    let optimistic: Profile;
    if (following || pending) {
      optimistic = {
        ...profile,
        relationship: "none",
        followers_count: following
          ? Math.max(0, profile.followers_count - 1)
          : profile.followers_count,
      };
    } else {
      const willBePending = profile.is_private;
      optimistic = {
        ...profile,
        relationship: willBePending ? "pending" : "following",
        followers_count: willBePending
          ? profile.followers_count
          : profile.followers_count + 1,
      };
    }
    onChange?.(optimistic);

    try {
      const path = `/api/v1/profiles/${encodeURIComponent(profile.handle)}/follow`;
      if (following || pending) await api.del(path);
      else await api.post(path);
    } catch (error) {
      onChange?.(profile);
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update follow state",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Button
      type="button"
      variant={variant}
      size="sm"
      className={cn("h-8 w-24 shrink-0 rounded-full text-xs", className)}
      aria-pressed={following}
      aria-label={following ? `Unfollow ${profile.name}` : `Follow ${profile.name}`}
      disabled={busy}
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        void toggle();
      }}
    >
      {label}
    </Button>
  );
}
