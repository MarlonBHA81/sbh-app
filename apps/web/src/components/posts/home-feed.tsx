"use client";

import { Newspaper, Users } from "lucide-react";
import Link from "next/link";
import { useRef, useState } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { withParam } from "@/lib/utils";

import { NearbyFeed } from "./nearby-feed";
import { PostList, type PostListHandle } from "./post-list";
import { PullToRefresh } from "./pull-to-refresh";

const TAB_STORAGE_KEY = "sbh.homeTab";
const TABS = ["for-you", "following", "nearby"] as const;

type FeedTab = (typeof TABS)[number];

function readPersistedTab(): FeedTab {
  if (typeof window === "undefined") return "for-you";
  try {
    const stored = window.localStorage.getItem(TAB_STORAGE_KEY);
    return stored && (TABS as readonly string[]).includes(stored)
      ? (stored as FeedTab)
      : "for-you";
  } catch {
    return "for-you";
  }
}

function persistTab(tab: FeedTab): void {
  try {
    window.localStorage.setItem(TAB_STORAGE_KEY, tab);
  } catch {
    // Storage unavailable — non-fatal.
  }
}

/** A single feed pane: pull-to-refresh wrapped cursor-paginated list. */
function FeedPane({
  endpoint,
  emptyState,
}: {
  endpoint: string;
  emptyState: React.ReactNode;
}) {
  const { mutationCount } = useComposer();
  const listRef = useRef<PostListHandle>(null);

  return (
    <PullToRefresh
      onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
    >
      <PostList
        ref={listRef}
        buildUrl={(cursor) =>
          cursor ? withParam(endpoint, "cursor", cursor) : endpoint
        }
        refreshKey={mutationCount}
        emptyState={emptyState}
      />
    </PullToRefresh>
  );
}

/**
 * Home feed tabs (For You | Following | Nearby). Panes stay mounted once
 * visited (forceMount + CSS hide) so lists and their scroll data survive
 * tab switches; the window scroll position is saved/restored per tab.
 */
export function HomeFeed() {
  const { openComposer } = useComposer();
  const [tab, setTab] = useState<FeedTab>(readPersistedTab);
  // Panes mount lazily on first visit, then stay mounted.
  const [visited, setVisited] = useState<readonly FeedTab[]>(() => [
    readPersistedTab(),
  ]);
  const scrollPositions = useRef<Partial<Record<FeedTab, number>>>({});

  function handleTabChange(value: string) {
    const next = value as FeedTab;
    if (next === tab) return;
    scrollPositions.current[tab] = window.scrollY;
    setTab(next);
    setVisited((prev) => (prev.includes(next) ? prev : [...prev, next]));
    persistTab(next);
    // Restore the target tab's scroll position after it becomes visible.
    requestAnimationFrame(() => {
      window.scrollTo({
        top: scrollPositions.current[next] ?? 0,
        behavior: "instant",
      });
    });
  }

  const paneClass = "pt-3 data-[state=inactive]:hidden";

  return (
    <Tabs value={tab} onValueChange={handleTabChange} className="gap-0">
      <div className="sticky top-14 z-30 -mx-4 border-b bg-background/90 px-4 py-2 backdrop-blur md:top-0">
        <TabsList className="w-full">
          <TabsTrigger value="for-you" className="h-10 flex-1">
            For You
          </TabsTrigger>
          <TabsTrigger value="following" className="h-10 flex-1">
            Following
          </TabsTrigger>
          <TabsTrigger value="nearby" className="h-10 flex-1">
            Nearby
          </TabsTrigger>
        </TabsList>
      </div>

      <TabsContent value="for-you" forceMount className={paneClass}>
        {visited.includes("for-you") ? (
          <FeedPane
            endpoint="/api/v1/feeds/for-you"
            emptyState={
              <EmptyState
                icon={Newspaper}
                title="Nothing here yet"
                description="Your personalized feed fills up as you follow people and topics."
              >
                <Button className="mt-2 h-11" onClick={() => openComposer()}>
                  Write a post
                </Button>
              </EmptyState>
            }
          />
        ) : null}
      </TabsContent>

      <TabsContent value="following" forceMount className={paneClass}>
        {visited.includes("following") ? (
          <FeedPane
            endpoint="/api/v1/feeds/following"
            emptyState={
              <EmptyState
                icon={Users}
                title="Your following feed is empty"
                description="Posts from people and businesses you follow show up here."
              >
                <Button asChild variant="outline" className="mt-2 h-11">
                  <Link href="/discover">Find topics to follow</Link>
                </Button>
              </EmptyState>
            }
          />
        ) : null}
      </TabsContent>

      <TabsContent value="nearby" forceMount className={paneClass}>
        {visited.includes("nearby") ? <NearbyFeed /> : null}
      </TabsContent>
    </Tabs>
  );
}
