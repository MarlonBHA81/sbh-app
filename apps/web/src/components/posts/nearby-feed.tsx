"use client";

import {
  Building2,
  LocateFixed,
  MapIcon,
  MapPin,
  MapPinOff,
  Navigation,
} from "lucide-react";
import Link from "next/link";
import { useRef, useState } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Slider } from "@/components/ui/slider";
import { ApiError } from "@/lib/api/client";
import type { LocalScope } from "@/lib/api/types";
import {
  MAX_RADIUS_KM,
  MIN_RADIUS_KM,
  useGeoStore,
} from "@/lib/stores/geo-store";
import { cn, withParam } from "@/lib/utils";

import { PostList, PostSkeleton, type PostListHandle } from "./post-list";
import { PullToRefresh } from "./pull-to-refresh";

const SCOPES: { value: LocalScope; label: string; icon: typeof MapPin }[] = [
  { value: "radius", label: "Radius", icon: Navigation },
  { value: "city", label: "My city", icon: MapPin },
  { value: "country", label: "My country", icon: Building2 },
];

/** Inline card shown when the local feed 422s (profile has no location set). */
function SetLocationCard() {
  return (
    <EmptyState
      icon={MapPinOff}
      title="Set your location"
      description="Share your location to see posts from your city or country. Your approximate location is used only to build these feeds."
    >
      <Button asChild className="mt-2 h-11 gap-2">
        <Link href="/map">
          <LocateFixed className="size-4" aria-hidden />
          Set location on the map
        </Link>
      </Button>
    </EmptyState>
  );
}

/** City / country local feed backed by GET /feeds/local?scope=. */
function LocalScopeFeed({ scope }: { scope: Exclude<LocalScope, "radius"> }) {
  const { mutationCount } = useComposer();
  const listRef = useRef<PostListHandle>(null);
  const endpoint = `/api/v1/feeds/local?scope=${scope}`;

  return (
    <PullToRefresh
      onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
    >
      <PostList
        ref={listRef}
        buildUrl={(cursor) =>
          cursor ? withParam(endpoint, "cursor", cursor) : endpoint
        }
        refreshKey={`${mutationCount}:${scope}`}
        renderError={(error, retry) =>
          error instanceof ApiError && error.status === 422 ? (
            <SetLocationCard />
          ) : (
            <div className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
              Couldn&apos;t load this feed.{" "}
              <button
                type="button"
                className="font-medium text-foreground underline-offset-4 hover:underline"
                onClick={retry}
              >
                Try again
              </button>
            </div>
          )
        }
        emptyState={
          <EmptyState
            icon={scope === "city" ? MapPin : Building2}
            title={scope === "city" ? "Nothing in your city yet" : "Nothing in your country yet"}
            description="Be the first to post something here."
          />
        }
      />
    </PullToRefresh>
  );
}

/** Radius feed: viewer's live location + adjustable radius (session only). */
function RadiusFeed() {
  const { mutationCount } = useComposer();
  const coords = useGeoStore((s) => s.coords);
  const status = useGeoStore((s) => s.status);
  const radiusKm = useGeoStore((s) => s.radiusKm);
  const requestLocation = useGeoStore((s) => s.requestLocation);
  const setRadiusKm = useGeoStore((s) => s.setRadiusKm);

  const [draftRadius, setDraftRadius] = useState(radiusKm);
  const listRef = useRef<PostListHandle>(null);

  if (status === "requesting") {
    return (
      <div className="flex flex-col gap-3">
        <PostSkeleton />
        <PostSkeleton />
        <PostSkeleton />
      </div>
    );
  }

  if (!coords) {
    if (status === "denied") {
      return (
        <EmptyState
          icon={MapPinOff}
          title="Location access denied"
          description="Nearby needs your location to find posts around you. Allow location access in your browser settings, then try again."
        >
          <Button variant="outline" className="mt-2 h-11" onClick={requestLocation}>
            Try again
          </Button>
        </EmptyState>
      );
    }

    if (status === "unavailable") {
      return (
        <EmptyState
          icon={MapPinOff}
          title="Location unavailable"
          description="We couldn't get your location right now. Check your device's location settings and try again."
        >
          <Button variant="outline" className="mt-2 h-11" onClick={requestLocation}>
            Try again
          </Button>
        </EmptyState>
      );
    }

    return (
      <EmptyState
        icon={MapPin}
        title="See what's happening nearby"
        description="Share your location to browse posts from businesses and people around you. It's only kept for this session and only used to fetch the feed."
      >
        <Button className="mt-2 h-11 gap-2" onClick={requestLocation}>
          <LocateFixed className="size-4" aria-hidden />
          Share location
        </Button>
      </EmptyState>
    );
  }

  const applied = draftRadius === radiusKm;
  const endpoint = `/api/v1/feeds/nearby?lat=${coords.lat.toFixed(5)}&lng=${coords.lng.toFixed(5)}&radius_km=${radiusKm}`;

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-3 rounded-xl border bg-card px-3 py-2.5">
        <MapPin className="size-4 shrink-0 text-muted-foreground" aria-hidden />
        <Slider
          value={[draftRadius]}
          min={MIN_RADIUS_KM}
          max={MAX_RADIUS_KM}
          step={1}
          onValueChange={([value]) => setDraftRadius(value)}
          aria-label="Search radius in kilometres"
          className="flex-1"
        />
        <Button
          type="button"
          variant={applied ? "outline" : "default"}
          size="sm"
          className="h-8 shrink-0 rounded-full px-3 text-xs tabular-nums"
          disabled={applied}
          onClick={() => setRadiusKm(draftRadius)}
        >
          {applied ? `${radiusKm} km` : `Update · ${draftRadius} km`}
        </Button>
      </div>

      <PullToRefresh
        onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
      >
        <PostList
          ref={listRef}
          buildUrl={(cursor) =>
            cursor ? withParam(endpoint, "cursor", cursor) : endpoint
          }
          refreshKey={`${mutationCount}:${radiusKm}:${coords.lat},${coords.lng}`}
          emptyState={
            <EmptyState
              icon={MapPin}
              title="Nothing nearby yet"
              description={`No posts within ${radiusKm} km. Try widening the radius.`}
            />
          }
        />
      </PullToRefresh>
    </div>
  );
}

/**
 * The Nearby tab: a scope selector (Radius | My city | My country) over the
 * radius feed and the city/country local feeds. The chosen scope is persisted
 * in the geo store. A link to the full nearby map sits in the header.
 */
export function NearbyFeed() {
  const scope = useGeoStore((s) => s.scope);
  const setScope = useGeoStore((s) => s.setScope);

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <div
          role="tablist"
          aria-label="Local feed scope"
          className="flex flex-1 gap-1.5 overflow-x-auto"
        >
          {SCOPES.map(({ value, label, icon: Icon }) => {
            const active = scope === value;
            return (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setScope(value)}
                className={cn(
                  "flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors",
                  active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
                )}
              >
                <Icon className="size-4" aria-hidden />
                {label}
              </button>
            );
          })}
        </div>
        <Button
          asChild
          variant="ghost"
          size="icon"
          className="size-9 shrink-0 rounded-full"
          aria-label="Open the nearby map"
          title="Nearby map"
        >
          <Link href="/map">
            <MapIcon className="size-5" aria-hidden />
          </Link>
        </Button>
      </div>

      {scope === "radius" ? (
        <RadiusFeed />
      ) : (
        <LocalScopeFeed scope={scope} />
      )}
    </div>
  );
}
