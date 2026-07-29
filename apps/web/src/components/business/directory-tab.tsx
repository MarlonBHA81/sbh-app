"use client";

import { BadgeCheck, MapPin, Search, SlidersHorizontal, Users, X } from "lucide-react";
import Link from "next/link";
import { useEffect, useMemo, useRef, useState } from "react";

import { CategoryChip } from "@/components/business/category-chip";
import { ProfileFollowButton } from "@/components/business/profile-follow-button";
import { ProfileList, type ProfileListHandle } from "@/components/business/profile-list";
import { EmptyState } from "@/components/empty-state";
import { ProfileAvatar } from "@/components/profile-avatar";
import { PullToRefresh } from "@/components/posts/pull-to-refresh";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Skeleton } from "@/components/ui/skeleton";
import { useBusinessCategories } from "@/hooks/use-business-categories";
import type { Profile } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn, withParam } from "@/lib/utils";

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

/** Best-effort split of a free-text "City, Country" location string. */
function splitLocation(location: string | null): { city: string; country: string } {
  if (!location) return { city: "", country: "" };
  const parts = location.split(",").map((p) => p.trim()).filter(Boolean);
  if (parts.length >= 2) {
    return { city: parts[0], country: parts[parts.length - 1] };
  }
  return { city: parts[0] ?? "", country: "" };
}

function DirectoryCard({
  profile,
  onChange,
}: {
  profile: Profile;
  onChange: (next: Profile) => void;
}) {
  return (
    <Link
      href={`/${profile.handle}`}
      className="flex items-start gap-3 rounded-xl border p-4 transition-colors hover:bg-accent/40"
    >
      <ProfileAvatar profile={profile} className="size-12 shrink-0" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <span className="flex items-center gap-1 font-medium leading-tight">
          <span className="truncate">{profile.name}</span>
          {profile.is_verified ? (
            <BadgeCheck
              className="size-4 shrink-0 text-teal-text"
              aria-label="Verified"
            />
          ) : null}
        </span>
        <span className="truncate text-xs text-muted-foreground">
          @{profile.handle}
        </span>
        {profile.business_category ? (
          <CategoryChip category={profile.business_category} className="mt-0.5" />
        ) : null}
        <span className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
          {profile.location ? (
            <span className="flex items-center gap-1">
              <MapPin className="size-3.5 shrink-0" aria-hidden />
              <span className="truncate">{profile.location}</span>
            </span>
          ) : null}
          <span className="flex items-center gap-1 tabular-nums">
            <Users className="size-3.5 shrink-0" aria-hidden />
            {formatCount(profile.followers_count)}
          </span>
        </span>
      </div>
      <ProfileFollowButton profile={profile} onChange={onChange} className="mt-0.5" />
    </Link>
  );
}

export function DirectoryTab() {
  const { categories, phase: catPhase } = useBusinessCategories();
  const activeProfile = useAuthStore((s) => s.activeProfile);

  const [category, setCategory] = useState<string | null>(null);
  const [rawQuery, setRawQuery] = useState("");
  const [query, setQuery] = useState("");
  const [country, setCountry] = useState("");
  const [city, setCity] = useState("");
  const [locOpen, setLocOpen] = useState(false);

  // Draft location inputs, prefilled from the active profile's location.
  const prefill = useMemo(
    () => splitLocation(activeProfile?.location ?? null),
    [activeProfile?.location],
  );
  const [draftCountry, setDraftCountry] = useState(prefill.country);
  const [draftCity, setDraftCity] = useState(prefill.city);

  const listRef = useRef<ProfileListHandle>(null);

  // Debounce the search query.
  useEffect(() => {
    const id = window.setTimeout(() => setQuery(rawQuery.trim()), 300);
    return () => window.clearTimeout(id);
  }, [rawQuery]);

  const endpoint = useMemo(() => {
    let url = "/api/v1/business/directory";
    if (category) url = withParam(url, "category", category);
    if (query) url = withParam(url, "q", query);
    if (country) url = withParam(url, "country", country);
    if (city) url = withParam(url, "city", city);
    return url;
  }, [category, query, country, city]);

  const hasLocation = Boolean(country || city);
  const hasFilters = Boolean(category || query || hasLocation);
  const refreshKey = endpoint;

  function openLocationPopover(open: boolean) {
    if (open) {
      setDraftCountry(country || prefill.country);
      setDraftCity(city || prefill.city);
    }
    setLocOpen(open);
  }

  function applyLocation() {
    setCountry(draftCountry.trim());
    setCity(draftCity.trim());
    setLocOpen(false);
  }

  function clearLocation() {
    setCountry("");
    setCity("");
    setDraftCountry("");
    setDraftCity("");
    setLocOpen(false);
  }

  const locationLabel = hasLocation
    ? [city, country].filter(Boolean).join(", ")
    : "Near me";

  return (
    <div className="flex flex-col gap-3">
      {/* Sticky filter row */}
      <div className="sticky top-14 z-20 -mx-4 flex flex-col gap-2 border-b bg-background/95 px-4 pt-1 pb-2 backdrop-blur md:top-0">
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <Search
              className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden
            />
            <Input
              value={rawQuery}
              onChange={(e) => setRawQuery(e.target.value)}
              placeholder="Search businesses"
              aria-label="Search businesses"
              className="h-10 pr-9 pl-9"
            />
            {rawQuery ? (
              <button
                type="button"
                onClick={() => setRawQuery("")}
                aria-label="Clear search"
                className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
              >
                <X className="size-4" aria-hidden />
              </button>
            ) : null}
          </div>
          <Popover open={locOpen} onOpenChange={openLocationPopover}>
            <PopoverTrigger asChild>
              <Button
                type="button"
                variant={hasLocation ? "default" : "outline"}
                size="sm"
                className="h-10 shrink-0 gap-1.5"
                aria-label="Filter by location"
              >
                <SlidersHorizontal className="size-4" aria-hidden />
                <span className="max-w-28 truncate">{locationLabel}</span>
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72">
              <div className="flex flex-col gap-3">
                <p className="text-sm font-medium">Filter by location</p>
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="dir-city" className="text-xs text-muted-foreground">
                    City
                  </label>
                  <Input
                    id="dir-city"
                    value={draftCity}
                    onChange={(e) => setDraftCity(e.target.value)}
                    placeholder="e.g. Sydney"
                    className="h-10"
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label
                    htmlFor="dir-country"
                    className="text-xs text-muted-foreground"
                  >
                    Country
                  </label>
                  <Input
                    id="dir-country"
                    value={draftCountry}
                    onChange={(e) => setDraftCountry(e.target.value)}
                    placeholder="e.g. Australia"
                    className="h-10"
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-9 flex-1"
                    onClick={clearLocation}
                  >
                    Clear
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    className="h-9 flex-1"
                    onClick={applyLocation}
                  >
                    Apply
                  </Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>

        {/* Category chips */}
        {catPhase === "loading" ? (
          <div className="flex gap-1.5 overflow-hidden">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-8 w-24 shrink-0 rounded-full" />
            ))}
          </div>
        ) : (
          <div className="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-0.5">
            {categories.map((cat) => {
              const active = category === cat.slug;
              return (
                <button
                  key={cat.id}
                  type="button"
                  aria-pressed={active}
                  onClick={() => setCategory(active ? null : cat.slug)}
                  className={cn(
                    "flex h-8 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors",
                    active
                      ? "border-primary bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
                  )}
                >
                  <span aria-hidden>{cat.icon ?? "🏢"}</span>
                  {cat.name}
                </button>
              );
            })}
          </div>
        )}
      </div>

      <PullToRefresh
        onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
      >
        <ProfileList
          ref={listRef}
          refreshKey={refreshKey}
          buildUrl={(cursor) =>
            cursor ? withParam(endpoint, "cursor", cursor) : endpoint
          }
          renderItem={(profile, helpers) => (
            <DirectoryCard
              profile={profile}
              onChange={(next) => helpers.replace(next)}
            />
          )}
          emptyState={
            <EmptyState
              icon={Search}
              title={hasFilters ? "No businesses found" : "No businesses yet"}
              description={
                hasFilters
                  ? "Try a different category, search term, or location."
                  : "Business profiles will appear here as they join."
              }
            >
              {hasFilters ? (
                <Button
                  variant="outline"
                  className="mt-2 h-10"
                  onClick={() => {
                    setCategory(null);
                    setRawQuery("");
                    setQuery("");
                    clearLocation();
                  }}
                >
                  Clear filters
                </Button>
              ) : null}
            </EmptyState>
          }
        />
      </PullToRefresh>
    </div>
  );
}
