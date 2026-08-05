"use client";

import { ChevronRight, PartyPopper } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { HomeCard, type HomeCardTier } from "@/components/home/home-card";
import { ProfileAvatar } from "@/components/profile-avatar";
import * as api from "@/lib/api/client";
import type { Paginated, Post } from "@/lib/api/types";

/**
 * "Recent wins" (V2 · BELONG): the latest few community wins, so members see
 * real milestones worth celebrating. Self-hides when there are none. Reuses the
 * wins feed — no new backend.
 */
export function RecentWinsCard({ tier }: { tier?: HomeCardTier }) {
  const [wins, setWins] = useState<Post[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Post>>("/api/v1/feeds/wins")
      .then((res) => {
        if (!cancelled) setWins(res.data.slice(0, 3));
      })
      .catch(() => {
        if (!cancelled) setWins([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (!wins || wins.length === 0) return null;

  return (
    <HomeCard
      tier={tier}
      tone="sage"
      icon={PartyPopper}
      title="Recent wins"
      aside={
        <Link
          href="/wins"
          className="flex items-center text-[13px] font-medium text-teal-text hover:underline"
        >
          See all
          <ChevronRight className="size-4" aria-hidden />
        </Link>
      }
    >
      <ul className="flex flex-col divide-y divide-warmgray">
        {wins.map((post) => (
          <li key={post.ulid}>
            <Link
              href={`/p/${post.ulid}`}
              className="flex items-center gap-3 py-2.5 active:scale-[0.99]"
            >
              <ProfileAvatar profile={post.profile} className="size-9 shrink-0" />
              <span className="flex min-w-0 flex-col">
                <span className="truncate text-sm font-medium text-text-primary">
                  {post.profile.name}
                </span>
                {post.body ? (
                  <span className="line-clamp-1 text-xs text-text-secondary">
                    {post.body}
                  </span>
                ) : null}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </HomeCard>
  );
}
