"use client";

import { ArrowRight, Hash, Search } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

import { ProfileAvatar } from "@/components/profile-avatar";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import * as api from "@/lib/api/client";
import type { SearchResults } from "@/lib/api/types";
import { useSearchStore } from "@/lib/stores/search-store";

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

/**
 * Command-palette global search: debounced GET /search into grouped People and
 * Topics results with keyboard navigation. Enter on a result navigates; the
 * footer row jumps to the full /search page for posts. A single instance lives
 * in the app shell; open state is shared via the search store (⌘K / Ctrl-K).
 */
export function SearchDialog() {
  const router = useRouter();
  const open = useSearchStore((s) => s.open);
  const setOpen = useSearchStore((s) => s.setOpen);

  const [query, setQuery] = useState("");
  const [results, setResults] = useState<SearchResults>({
    profiles: [],
    topics: [],
  });
  const [loading, setLoading] = useState(false);
  const seqRef = useRef(0);

  // Global ⌘K / Ctrl-K to open the palette.
  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === "k" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        useSearchStore.getState().setOpen(true);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Debounced combined search. All state writes happen in async callbacks
  // (never synchronously in the effect body).
  useEffect(() => {
    const trimmed = query.trim();
    const seq = ++seqRef.current;
    const timer = window.setTimeout(() => {
      if (!trimmed) {
        setResults({ profiles: [], topics: [] });
        setLoading(false);
        return;
      }
      setLoading(true);
      api
        .get<SearchResults>(`/api/v1/search?q=${encodeURIComponent(trimmed)}`)
        .then((res) => {
          if (seqRef.current === seq) {
            setResults({
              profiles: res.profiles ?? [],
              topics: res.topics ?? [],
            });
            setLoading(false);
          }
        })
        .catch(() => {
          if (seqRef.current === seq) {
            setResults({ profiles: [], topics: [] });
            setLoading(false);
          }
        });
    }, trimmed ? 200 : 0);
    return () => window.clearTimeout(timer);
  }, [query]);

  function close() {
    setOpen(false);
    // Reset after the close animation so results don't flash on reopen.
    window.setTimeout(() => {
      setQuery("");
      setResults({ profiles: [], topics: [] });
    }, 200);
  }

  function go(href: string) {
    close();
    router.push(href);
  }

  const trimmed = query.trim();
  const hasResults =
    results.profiles.length > 0 || results.topics.length > 0;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (next) setOpen(true);
        else close();
      }}
    >
      <DialogContent
        showCloseButton={false}
        className="overflow-hidden p-0 sm:max-w-lg"
      >
        <DialogHeader className="sr-only">
          <DialogTitle>Search</DialogTitle>
          <DialogDescription>
            Search people and topics, or search posts.
          </DialogDescription>
        </DialogHeader>
        <Command shouldFilter={false} className="rounded-lg">
          <div className="flex h-12 items-center gap-2 border-b px-3">
            <Search className="size-4 shrink-0 text-muted-foreground" aria-hidden />
            <input
              autoFocus
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search people and topics"
              aria-label="Search people and topics"
              className="h-full w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>
          <CommandList className="max-h-[60vh]">
            {!trimmed ? (
              <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                Search for people and topics.
              </p>
            ) : null}

            {trimmed && !loading && !hasResults ? (
              <CommandEmpty>No people or topics found.</CommandEmpty>
            ) : null}

            {results.profiles.length > 0 ? (
              <CommandGroup heading="People">
                {results.profiles.map((profile) => (
                  <CommandItem
                    key={profile.ulid}
                    value={`profile-${profile.ulid}`}
                    onSelect={() => go(`/${profile.handle}`)}
                    className="gap-3"
                  >
                    <ProfileAvatar profile={profile} className="size-8" />
                    <span className="flex min-w-0 flex-col">
                      <span className="truncate text-sm font-medium">
                        {profile.name}
                      </span>
                      <span className="truncate text-xs text-muted-foreground">
                        @{profile.handle}
                      </span>
                    </span>
                  </CommandItem>
                ))}
              </CommandGroup>
            ) : null}

            {results.topics.length > 0 ? (
              <CommandGroup heading="Topics">
                {results.topics.map((topic) => (
                  <CommandItem
                    key={topic.slug}
                    value={`topic-${topic.slug}`}
                    onSelect={() =>
                      go(`/topics/${encodeURIComponent(topic.slug)}`)
                    }
                    className="gap-3"
                  >
                    <span
                      className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-base"
                      aria-hidden
                    >
                      {topic.icon ?? <Hash className="size-4" />}
                    </span>
                    <span className="flex min-w-0 flex-col">
                      <span className="truncate text-sm font-medium">
                        {topic.name}
                      </span>
                      <span className="truncate text-xs text-muted-foreground tabular-nums">
                        {formatCount(topic.followers_count)}{" "}
                        {topic.followers_count === 1 ? "follower" : "followers"}
                      </span>
                    </span>
                  </CommandItem>
                ))}
              </CommandGroup>
            ) : null}

            {trimmed ? (
              <CommandGroup>
                <CommandItem
                  value="__search_posts__"
                  onSelect={() => go(`/search?q=${encodeURIComponent(trimmed)}`)}
                  className="gap-2 text-sm"
                >
                  <Search className="size-4" aria-hidden />
                  <span className="min-w-0 flex-1 truncate">
                    Search posts for{" "}
                    <span className="font-medium text-foreground">
                      &ldquo;{trimmed}&rdquo;
                    </span>
                  </span>
                  <ArrowRight className="size-4 shrink-0" aria-hidden />
                </CommandItem>
              </CommandGroup>
            ) : null}
          </CommandList>
        </Command>
      </DialogContent>
    </Dialog>
  );
}
