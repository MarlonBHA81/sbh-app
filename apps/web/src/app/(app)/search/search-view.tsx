"use client";

import { Hash, Search, SearchX } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { PostList } from "@/components/posts/post-list";
import { ProfileAvatar } from "@/components/profile-avatar";
import { RankChip, findRankBadge } from "@/components/gamification/rank-chip";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type { SearchResults } from "@/lib/api/types";
import { withParam } from "@/lib/utils";

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

function ResultSkeleton() {
  return (
    <div className="divide-y rounded-xl border">
      {Array.from({ length: 5 }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 px-3 py-3">
          <Skeleton className="size-10 rounded-full" />
          <div className="flex flex-1 flex-col gap-1.5">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-3 w-20" />
          </div>
        </div>
      ))}
    </div>
  );
}

/**
 * Full search page: a query box synced to the ?q= URL param (shareable) with
 * People | Topics | Posts tabs. People/Topics come from the combined /search
 * endpoint; Posts is a cursor-paginated /search/posts list (min 2 chars).
 */
export function SearchView() {
  const router = useRouter();
  const params = useSearchParams();
  const initialQuery = params.get("q") ?? "";

  const [query, setQuery] = useState(initialQuery);
  const [tab, setTab] = useState<"people" | "topics" | "posts">("people");
  const [results, setResults] = useState<SearchResults>({
    profiles: [],
    topics: [],
  });
  const [phase, setPhase] = useState<"idle" | "loading" | "loaded" | "error">(
    initialQuery.trim() ? "loading" : "idle",
  );
  const seqRef = useRef(0);

  const trimmed = query.trim();

  // Keep the URL in sync (replace = shareable, no history spam).
  useEffect(() => {
    const target = trimmed ? `/search?q=${encodeURIComponent(trimmed)}` : "/search";
    router.replace(target, { scroll: false });
  }, [trimmed, router]);

  // Combined people + topics search backs the People and Topics tabs. All
  // state writes happen in async callbacks (never in the effect body).
  useEffect(() => {
    const seq = ++seqRef.current;
    const timer = window.setTimeout(() => {
      if (!trimmed) {
        setResults({ profiles: [], topics: [] });
        setPhase("idle");
        return;
      }
      setPhase("loading");
      api
        .get<SearchResults>(`/api/v1/search?q=${encodeURIComponent(trimmed)}`)
        .then((res) => {
          if (seqRef.current !== seq) return;
          setResults({
            profiles: res.profiles ?? [],
            topics: res.topics ?? [],
          });
          setPhase("loaded");
        })
        .catch(() => {
          if (seqRef.current === seq) setPhase("error");
        });
    }, trimmed ? 250 : 0);
    return () => window.clearTimeout(timer);
  }, [trimmed]);

  const postsQueryTooShort = trimmed.length < 2;

  return (
    <div className="flex flex-col gap-4">
      <div className="relative">
        <Search
          className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search people, topics and posts"
          aria-label="Search"
          autoFocus
          className="h-11 pl-9"
        />
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
        <TabsList className="w-full">
          <TabsTrigger value="people" className="h-10 flex-1">
            People
          </TabsTrigger>
          <TabsTrigger value="topics" className="h-10 flex-1">
            Topics
          </TabsTrigger>
          <TabsTrigger value="posts" className="h-10 flex-1">
            Posts
          </TabsTrigger>
        </TabsList>

        {!trimmed ? (
          <div className="pt-6">
            <EmptyState
              icon={Search}
              title="Search SBH Community"
              description="Find people, topics and posts across the community."
            />
          </div>
        ) : null}

        {/* People */}
        <TabsContent value="people" className="pt-4">
          {trimmed && phase === "loading" ? <ResultSkeleton /> : null}
          {trimmed && phase === "loaded" && results.profiles.length === 0 ? (
            <EmptyState icon={SearchX} title="No people found" />
          ) : null}
          {results.profiles.length > 0 ? (
            <ul className="divide-y overflow-hidden rounded-xl border">
              {results.profiles.map((profile) => {
                const rank = findRankBadge(profile);
                return (
                  <li key={profile.ulid}>
                    <Link
                      href={`/${profile.handle}`}
                      className="flex items-center gap-3 px-3 py-3 transition-colors hover:bg-accent/40"
                    >
                      <ProfileAvatar profile={profile} className="size-10" />
                      <span className="flex min-w-0 flex-1 flex-col">
                        <span className="flex items-center gap-2">
                          <span className="truncate text-sm font-medium">
                            {profile.name}
                          </span>
                          {rank ? (
                            <RankChip badge={rank} className="shrink-0" />
                          ) : null}
                        </span>
                        <span className="truncate text-xs text-muted-foreground">
                          @{profile.handle}
                        </span>
                      </span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          ) : null}
        </TabsContent>

        {/* Topics */}
        <TabsContent value="topics" className="pt-4">
          {trimmed && phase === "loading" ? <ResultSkeleton /> : null}
          {trimmed && phase === "loaded" && results.topics.length === 0 ? (
            <EmptyState icon={SearchX} title="No topics found" />
          ) : null}
          {results.topics.length > 0 ? (
            <ul className="divide-y overflow-hidden rounded-xl border">
              {results.topics.map((topic) => (
                <li key={topic.slug}>
                  <Link
                    href={`/topics/${encodeURIComponent(topic.slug)}`}
                    className="flex items-center gap-3 px-3 py-3 transition-colors hover:bg-accent/40"
                  >
                    <span
                      className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-lg"
                      aria-hidden
                    >
                      {topic.icon ?? <Hash className="size-5" />}
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col">
                      <span className="truncate text-sm font-medium">
                        {topic.name}
                      </span>
                      <span className="truncate text-xs text-muted-foreground tabular-nums">
                        {formatCount(topic.followers_count)}{" "}
                        {topic.followers_count === 1 ? "follower" : "followers"}
                      </span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          ) : null}
        </TabsContent>

        {/* Posts */}
        <TabsContent value="posts" className="pt-4">
          {!trimmed ? null : postsQueryTooShort ? (
            <EmptyState
              icon={Search}
              title="Keep typing"
              description="Enter at least 2 characters to search posts."
            />
          ) : (
            <PostList
              key={trimmed}
              buildUrl={(cursor) => {
                const base = `/api/v1/search/posts?q=${encodeURIComponent(trimmed)}`;
                return cursor ? withParam(base, "cursor", cursor) : base;
              }}
              emptyState={
                <EmptyState
                  icon={SearchX}
                  title="No posts found"
                  description={`Nothing matched “${trimmed}”.`}
                />
              }
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
