"use client";

import { Search } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { ProfileAvatar } from "@/components/profile-avatar";
import { Input } from "@/components/ui/input";
import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/**
 * Debounced profile search box backed by GET /search/profiles. Selecting a
 * result invokes `onSelect`; `excludeUlids` hides already-chosen profiles.
 * Presentation only — the caller decides what selection does (navigate / chip).
 */
export function ProfileSearch({
  onSelect,
  excludeUlids,
  placeholder = "Search people",
  autoFocus,
}: {
  onSelect: (profile: Profile) => void;
  excludeUlids?: readonly string[];
  placeholder?: string;
  autoFocus?: boolean;
}) {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<Profile[]>([]);
  const [loading, setLoading] = useState(false);
  const seqRef = useRef(0);
  const exclude = new Set(excludeUlids ?? []);

  useEffect(() => {
    const trimmed = query.trim();
    // Empty query: results are hidden by the render gate; leave state as-is
    // (a new query supersedes it via the sequence guard).
    if (!trimmed) return;
    const seq = ++seqRef.current;
    const timer = window.setTimeout(() => {
      setLoading(true);
      api
        .get<{ data: Profile[] }>(
          `/api/v1/search/profiles?q=${encodeURIComponent(trimmed)}`,
        )
        .then((res) => {
          if (seqRef.current === seq) {
            setResults(res.data);
            setLoading(false);
          }
        })
        .catch(() => {
          if (seqRef.current === seq) {
            setResults([]);
            setLoading(false);
          }
        });
    }, 200);
    return () => window.clearTimeout(timer);
  }, [query]);

  const visible = results.filter((p) => !exclude.has(p.ulid));

  return (
    <div className="flex flex-col gap-2">
      <div className="relative">
        <Search
          className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={placeholder}
          autoFocus={autoFocus}
          className="h-11 pl-9"
          aria-label={placeholder}
        />
      </div>

      {query.trim() ? (
        <ul className="flex max-h-72 flex-col gap-0.5 overflow-y-auto">
          {visible.map((profile) => (
            <li key={profile.ulid}>
              <button
                type="button"
                onClick={() => {
                  onSelect(profile);
                  setQuery("");
                  setResults([]);
                }}
                className={cn(
                  "flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-accent/60",
                )}
              >
                <ProfileAvatar profile={profile} className="size-9" />
                <span className="flex min-w-0 flex-col">
                  <span className="truncate text-sm font-medium">
                    {profile.name}
                  </span>
                  <span className="truncate text-xs text-muted-foreground">
                    @{profile.handle}
                  </span>
                </span>
              </button>
            </li>
          ))}
          {!loading && visible.length === 0 ? (
            <li className="px-2 py-6 text-center text-sm text-muted-foreground">
              No people found
            </li>
          ) : null}
        </ul>
      ) : null}
    </div>
  );
}
