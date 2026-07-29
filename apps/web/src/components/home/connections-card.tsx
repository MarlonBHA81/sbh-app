"use client";

import { UserPlus } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { ProfileFollowButton } from "@/components/business/profile-follow-button";
import { HomeCard, type HomeCardTier } from "@/components/home/home-card";
import { ProfileAvatar } from "@/components/profile-avatar";
import * as api from "@/lib/api/client";
import type { ConnectionSuggestion, Profile } from "@/lib/api/types";

/**
 * "Today's Connections" (V1 · CONNECT): a few people to meet, each with a
 * reason, and a one-tap follow. A preview of the Daily Home dashboard card.
 */
export function ConnectionsCard({ tier }: { tier?: HomeCardTier }) {
  const [items, setItems] = useState<ConnectionSuggestion[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: ConnectionSuggestion[] }>("/api/v1/me/connections")
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

  // Hide entirely until there's something worth showing.
  if (!items || items.length === 0) return null;

  function onFollowChange(next: Profile) {
    // Drop a profile from the list once followed, so the card stays fresh.
    if (next.relationship === "following" || next.relationship === "pending") {
      setItems((prev) => prev?.filter((s) => s.profile.ulid !== next.ulid) ?? prev);
    }
  }

  return (
    <HomeCard tier={tier} tone="plum" icon={UserPlus} title="People to meet today">
      <ul className="flex flex-col divide-y divide-warmgray">
        {items.map(({ profile, reason }) => (
          <li key={profile.ulid} className="flex items-center gap-3 py-2.5">
            <Link href={`/${profile.handle}`} className="shrink-0">
              <ProfileAvatar profile={profile} className="size-10" />
            </Link>
            <Link href={`/${profile.handle}`} className="flex min-w-0 flex-1 flex-col">
              <span className="truncate font-heading text-sm font-medium text-text-primary">
                {profile.name}
              </span>
              <span className="truncate text-xs text-text-secondary">{reason}</span>
            </Link>
            <ProfileFollowButton
              profile={profile}
              onChange={onFollowChange}
              className="h-9 shrink-0 px-4"
            />
          </li>
        ))}
      </ul>
    </HomeCard>
  );
}
