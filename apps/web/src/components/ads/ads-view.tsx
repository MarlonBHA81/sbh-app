"use client";

import { Megaphone } from "lucide-react";
import { useCallback, useEffect, useState } from "react";

import { CampaignCard } from "@/components/ads/campaign-card";
import { usePromote } from "@/components/ads/promote-provider";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Campaign, Paginated } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

interface ListState {
  /** Identity of the loaded list (the campaign mutation counter). */
  key: number;
  phase: "loading" | "loaded" | "error";
  campaigns: Campaign[];
  nextCursor: string | null;
}

export function AdsView() {
  const { openPromote, campaignMutationCount } = usePromote();
  const isAdmin = useAuthStore((st) => Boolean(st.user?.is_admin));
  const [state, setState] = useState<ListState>({
    key: campaignMutationCount,
    phase: "loading",
    campaigns: [],
    nextCursor: null,
  });
  const [loadingMore, setLoadingMore] = useState(false);

  // Reset while rendering when a campaign was created elsewhere (post-list
  // pattern) — no synchronous setState inside the effect.
  if (state.key !== campaignMutationCount) {
    setState({
      key: campaignMutationCount,
      phase: "loading",
      campaigns: [],
      nextCursor: null,
    });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Campaign>>("/api/v1/ads/campaigns")
      .then((res) => {
        if (cancelled) return;
        setState({
          key: campaignMutationCount,
          phase: "loaded",
          campaigns: res.data,
          nextCursor: res.meta.next_cursor,
        });
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            key: campaignMutationCount,
            phase: "error",
            campaigns: [],
            nextCursor: null,
          });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [campaignMutationCount]);

  const { phase, campaigns, nextCursor } = state;

  const loadMore = useCallback(async () => {
    if (!nextCursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<Campaign>>(
        `/api/v1/ads/campaigns?cursor=${encodeURIComponent(nextCursor)}`,
      );
      setState((prev) => {
        const seen = new Set(prev.campaigns.map((c) => c.ulid));
        return {
          ...prev,
          campaigns: [
            ...prev.campaigns,
            ...res.data.filter((c) => !seen.has(c.ulid)),
          ],
          nextCursor: res.meta.next_cursor,
        };
      });
    } catch {
      // Keep the current list; the button stays for a retry.
    } finally {
      setLoadingMore(false);
    }
  }, [nextCursor, loadingMore]);

  const handleUpdated = useCallback((updated: Campaign) => {
    setState((prev) => ({
      ...prev,
      campaigns: prev.campaigns.map((c) =>
        c.ulid === updated.ulid ? updated : c,
      ),
    }));
  }, []);


  // The Ad Center is an admin-only tool; regular accounts see a notice.
  if (!isAdmin) {
    return (
      <EmptyState
        icon={Megaphone}
        title="Ad Center is admin-only"
        description="Promoting posts is currently limited to administrators. Contact an admin if you'd like a post promoted."
      />
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex flex-col">
          <h1 className="text-xl font-semibold tracking-tight">Ad Center</h1>
          <p className="text-sm text-muted-foreground">
            Promote your posts and track their performance.
          </p>
        </div>
        <Button className="h-10 shrink-0" onClick={() => openPromote()}>
          <Megaphone className="size-4" aria-hidden />
          Promote a post
        </Button>
      </div>

      {phase === "loading" ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-52 w-full rounded-xl" />
          ))}
        </div>
      ) : phase === "error" ? (
        <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
          Couldn&apos;t load your campaigns.
        </div>
      ) : campaigns.length === 0 ? (
        <EmptyState
          icon={Megaphone}
          title="No campaigns yet"
          description="Promote your posts to reach more people in the For You feed."
        >
          <Button className="mt-2 h-11" onClick={() => openPromote()}>
            <Megaphone className="size-4" aria-hidden />
            Promote a post
          </Button>
        </EmptyState>
      ) : (
        <div className="flex flex-col gap-3">
          {campaigns.map((campaign) => (
            <CampaignCard
              key={campaign.ulid}
              campaign={campaign}
              onUpdated={handleUpdated}
            />
          ))}
          {nextCursor ? (
            <Button
              type="button"
              variant="outline"
              className="h-11"
              disabled={loadingMore}
              onClick={() => void loadMore()}
            >
              {loadingMore ? "Loading…" : "Load more"}
            </Button>
          ) : null}
        </div>
      )}
    </div>
  );
}
