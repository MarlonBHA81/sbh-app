"use client";

import { Compass } from "lucide-react";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { OpportunityCard } from "@/components/opportunities/opportunity-card";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type { Opportunity, OpportunityType, Paginated } from "@/lib/api/types";
import { OPPORTUNITY_TYPES } from "@/lib/opportunities";
import { cn } from "@/lib/utils";

type Tab = "browse" | "saved";

function buildUrl(tab: Tab, type: OpportunityType | null, cursor: string | null) {
  const base =
    tab === "saved" ? "/api/v1/me/opportunities/saved" : "/api/v1/opportunities";
  const params = new URLSearchParams();
  if (tab === "browse" && type) params.set("type", type);
  if (cursor) params.set("cursor", cursor);
  const qs = params.toString();
  return `${base}${qs ? `?${qs}` : ""}`;
}

/**
 * One feed instance. Remounted (via key) when the tab/type changes, so the
 * skeleton shows on every filter switch and the effect only setStates
 * asynchronously (never synchronously in the effect body).
 */
function OpportunityFeed({
  tab,
  type,
}: {
  tab: Tab;
  type: OpportunityType | null;
}) {
  const [items, setItems] = useState<Opportunity[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Opportunity>>(buildUrl(tab, type, null))
      .then((res) => {
        if (cancelled) return;
        setItems(res.data);
        setCursor(res.meta.next_cursor);
      })
      .catch(() => {
        if (!cancelled) setItems([]);
      });
    return () => {
      cancelled = true;
    };
  }, [tab, type]);

  async function loadMore() {
    if (!cursor || busy) return;
    setBusy(true);
    try {
      const res = await api.get<Paginated<Opportunity>>(
        buildUrl(tab, type, cursor),
      );
      setItems((prev) => [...(prev ?? []), ...res.data]);
      setCursor(res.meta.next_cursor);
    } catch {
      // Keep what we have; the button stays for a retry.
    } finally {
      setBusy(false);
    }
  }

  function onSavedChange(ulid: string, saved: boolean) {
    if (tab === "saved" && !saved) {
      setItems((prev) => prev?.filter((o) => o.ulid !== ulid) ?? prev);
    }
  }

  if (items === null) {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-40 w-full rounded-(--radius-card)" />
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <EmptyState
        icon={Compass}
        title={tab === "saved" ? "Nothing saved yet" : "No opportunities yet"}
        description={
          tab === "saved"
            ? "Tap the bookmark on any opportunity to keep it here."
            : "New tenders, funding and grants will appear here as they're added."
        }
      />
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {items.map((o) => (
        <OpportunityCard
          key={o.ulid}
          opportunity={o}
          onSavedChange={onSavedChange}
        />
      ))}
      {cursor ? (
        <Button
          type="button"
          variant="outline"
          className="h-11"
          disabled={busy}
          onClick={() => void loadMore()}
        >
          {busy ? "Loading…" : "Load more"}
        </Button>
      ) : null}
    </div>
  );
}

function BrowseTab() {
  const [type, setType] = useState<OpportunityType | null>(null);
  return (
    <div className="flex flex-col gap-3">
      <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button
          type="button"
          onClick={() => setType(null)}
          className={cn(
            "shrink-0 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors",
            type === null
              ? "border-teal bg-teal text-white"
              : "border-warmgray bg-card text-text-primary hover:bg-accent",
          )}
        >
          All
        </button>
        {OPPORTUNITY_TYPES.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setType(t.key)}
            className={cn(
              "shrink-0 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors",
              type === t.key
                ? "border-teal bg-teal text-white"
                : "border-warmgray bg-card text-text-primary hover:bg-accent",
            )}
          >
            {t.label}
          </button>
        ))}
      </div>
      <OpportunityFeed key={type ?? "all"} tab="browse" type={type} />
    </div>
  );
}

/** Opportunities feed (V1 · GROW): browse + saved. */
export function OpportunitiesView() {
  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Opportunities" />
      <p className="text-sm text-text-secondary">
        Tenders, funding, grants and programmes — brought to you.
      </p>
      <Tabs defaultValue="browse">
        <TabsList className="w-full">
          <TabsTrigger value="browse" className="h-10 flex-1">
            Browse
          </TabsTrigger>
          <TabsTrigger value="saved" className="h-10 flex-1">
            Saved
          </TabsTrigger>
        </TabsList>
        <TabsContent value="browse" className="pt-3">
          <BrowseTab />
        </TabsContent>
        <TabsContent value="saved" className="pt-3">
          <OpportunityFeed tab="saved" type={null} />
        </TabsContent>
      </Tabs>
    </div>
  );
}
