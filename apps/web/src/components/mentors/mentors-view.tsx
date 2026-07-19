"use client";

import { MessageCircle, Users } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { ProfileFollowButton } from "@/components/business/profile-follow-button";
import { ProfileAvatar } from "@/components/profile-avatar";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useStartDm } from "@/hooks/use-start-dm";
import * as api from "@/lib/api/client";
import type { ConnectionSuggestion } from "@/lib/api/types";

function MentorRow({ profile, reason }: ConnectionSuggestion) {
  const { startDm, busyUlid } = useStartDm();

  return (
    <article className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-3">
        <Link href={`/${profile.handle}`} className="shrink-0">
          <ProfileAvatar profile={profile} className="size-11" />
        </Link>
        <Link href={`/${profile.handle}`} className="flex min-w-0 flex-1 flex-col">
          <span className="truncate font-heading text-sm font-semibold text-text-primary">
            {profile.name}
          </span>
          <span className="truncate text-xs text-text-secondary">{reason}</span>
          {profile.category ? (
            <span className="truncate text-xs text-text-secondary">
              {profile.category}
            </span>
          ) : null}
        </Link>
      </div>
      <div className="flex gap-2">
        <ProfileFollowButton profile={profile} className="h-9 flex-1" />
        <Button
          type="button"
          variant="outline"
          className="h-9 flex-1 gap-1.5"
          disabled={busyUlid === profile.ulid}
          onClick={() => void startDm(profile)}
        >
          <MessageCircle className="size-4" aria-hidden />
          Message
        </Button>
      </div>
    </article>
  );
}

/** Mentor matching (V2 · CONNECT): mentors ranked by relevance to the member. */
export function MentorsView() {
  const [items, setItems] = useState<ConnectionSuggestion[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: ConnectionSuggestion[] }>("/api/v1/mentors")
      .then((res) => {
        if (!cancelled) setItems(res.data);
      })
      .catch(() => {
        if (!cancelled) setItems([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Mentors" />
      <p className="text-sm text-text-secondary">
        Experienced members who&apos;ve opted in to help — matched to your
        industry and stage.
      </p>

      {items === null ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-28 w-full rounded-(--radius-card)" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <EmptyState
          icon={Users}
          title="No mentors yet"
          description="As members opt in to mentoring, they'll appear here matched to you."
        />
      ) : (
        <div className="flex flex-col gap-3">
          {items.map((item) => (
            <MentorRow key={item.profile.ulid} {...item} />
          ))}
        </div>
      )}
    </div>
  );
}
