"use client";

import { LocateFixed, MapPin, MapPinOff } from "lucide-react";
import { useRef, useState } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Slider } from "@/components/ui/slider";
import {
  MAX_RADIUS_KM,
  MIN_RADIUS_KM,
  useGeoStore,
} from "@/lib/stores/geo-store";
import { withParam } from "@/lib/utils";

import { PostList, PostSkeleton, type PostListHandle } from "./post-list";
import { PullToRefresh } from "./pull-to-refresh";

export function NearbyFeed() {
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
          <Button
            variant="outline"
            className="mt-2 h-11"
            onClick={requestLocation}
          >
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
          <Button
            variant="outline"
            className="mt-2 h-11"
            onClick={requestLocation}
          >
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
