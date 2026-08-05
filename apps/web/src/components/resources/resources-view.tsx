"use client";

import { BookOpen, Search } from "lucide-react";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { ResourceCard } from "@/components/resources/resource-card";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type {
  LibraryResource,
  Paginated,
  ResourceCategory,
} from "@/lib/api/types";
import { RESOURCE_CATEGORIES } from "@/lib/resources";
import { cn } from "@/lib/utils";

type Tab = "browse" | "saved";

function buildUrl(
  tab: Tab,
  category: ResourceCategory | null,
  query: string,
  cursor: string | null,
) {
  const base =
    tab === "saved" ? "/api/v1/me/resources/saved" : "/api/v1/resources";
  const params = new URLSearchParams();
  if (tab === "browse" && category) params.set("category", category);
  if (tab === "browse" && query.trim()) params.set("q", query.trim());
  if (cursor) params.set("cursor", cursor);
  const qs = params.toString();
  return `${base}${qs ? `?${qs}` : ""}`;
}

/**
 * One feed instance. Remounted (via key) when the tab/category/query changes,
 * so the skeleton shows on every filter switch and the effect only setStates
 * asynchronously.
 */
function ResourceFeed({
  tab,
  category,
  query,
}: {
  tab: Tab;
  category: ResourceCategory | null;
  query: string;
}) {
  const [items, setItems] = useState<LibraryResource[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<LibraryResource>>(buildUrl(tab, category, query, null))
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
  }, [tab, category, query]);

  async function loadMore() {
    if (!cursor || busy) return;
    setBusy(true);
    try {
      const res = await api.get<Paginated<LibraryResource>>(
        buildUrl(tab, category, query, cursor),
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
      setItems((prev) => prev?.filter((r) => r.ulid !== ulid) ?? prev);
    }
  }

  if (items === null) {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-36 w-full rounded-(--radius-card)" />
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <EmptyState
        icon={BookOpen}
        title={
          tab === "saved"
            ? "Nothing saved yet"
            : query.trim()
              ? "No matches"
              : "No resources yet"
        }
        description={
          tab === "saved"
            ? "Tap the bookmark on any resource to keep it here."
            : query.trim()
              ? "Try a different search or clear the filters."
              : "Templates, checklists and toolkits will appear here as they're added."
        }
      />
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {items.map((r) => (
        <ResourceCard key={r.ulid} resource={r} onSavedChange={onSavedChange} />
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
  const [category, setCategory] = useState<ResourceCategory | null>(null);
  const [input, setInput] = useState("");
  const [query, setQuery] = useState("");

  // Debounce the search so we don't refetch on every keystroke.
  useEffect(() => {
    const id = setTimeout(() => setQuery(input), 300);
    return () => clearTimeout(id);
  }, [input]);

  return (
    <div className="flex flex-col gap-3">
      <div className="relative">
        <Search
          className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-text-secondary"
          aria-hidden
        />
        <Input
          type="search"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Search resources"
          aria-label="Search resources"
          className="ps-9"
        />
      </div>
      <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button
          type="button"
          onClick={() => setCategory(null)}
          className={cn(
            "shrink-0 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors",
            category === null
              ? "border-teal bg-teal text-white"
              : "border-warmgray bg-card text-text-primary hover:bg-accent",
          )}
        >
          All
        </button>
        {RESOURCE_CATEGORIES.map((c) => (
          <button
            key={c.key}
            type="button"
            onClick={() => setCategory(c.key)}
            className={cn(
              "shrink-0 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors",
              category === c.key
                ? "border-teal bg-teal text-white"
                : "border-warmgray bg-card text-text-primary hover:bg-accent",
            )}
          >
            {c.label}
          </button>
        ))}
      </div>
      <ResourceFeed
        key={`${category ?? "all"}:${query}`}
        tab="browse"
        category={category}
        query={query}
      />
    </div>
  );
}

/** Resource Library (V2 · LEARN): browse + saved. */
export function ResourcesView() {
  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Resources" />
      <p className="text-sm text-text-secondary">
        Templates, checklists, toolkits and AI prompts — ready to use.
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
          <ResourceFeed tab="saved" category={null} query="" />
        </TabsContent>
      </Tabs>
    </div>
  );
}
