"use client";

import dynamic from "next/dynamic";
import {
  LocateFixed,
  MapIcon,
  MapPin,
  MapPinOff,
  MessageCircle,
  ShieldCheck,
  UserRound,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { RankChip, findRankBadge } from "@/components/gamification/rank-chip";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerHeader,
  DrawerTitle,
} from "@/components/ui/drawer";
import { Skeleton } from "@/components/ui/skeleton";
import { Slider } from "@/components/ui/slider";
import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type { Conversation, NearbyUser } from "@/lib/api/types";
import { useEchoPresence } from "@/lib/echo";
import { geohashEncode } from "@/lib/geo";
import {
  MAX_RADIUS_KM,
  MIN_RADIUS_KM,
  useGeoStore,
} from "@/lib/stores/geo-store";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";
import { useSettingsStore } from "@/lib/stores/settings-store";
import { useLocationSharing } from "@/lib/use-location-sharing";

// pigeon-maps touches the DOM on mount — load it client-only.
const NearbyMap = dynamic(
  () => import("@/components/map/nearby-map").then((m) => m.NearbyMap),
  { ssr: false, loading: () => <Skeleton className="size-full" /> },
);

/** Pick a sensible initial zoom for the chosen radius (smaller = closer). */
function zoomForRadius(radiusKm: number): number {
  const z = Math.round(14 - Math.log2(Math.max(1, radiusKm)));
  return Math.min(15, Math.max(6, z));
}

/** Best-effort profile ulid from a presence member payload. */
function memberUlid(member: unknown): string | null {
  if (typeof member === "string") return member;
  const r = member as Record<string, unknown> | null;
  if (!r) return null;
  for (const key of ["ulid", "profile_ulid", "id"]) {
    if (typeof r[key] === "string") return r[key] as string;
  }
  return null;
}

function OptInBanner() {
  const { busy, enable } = useLocationSharing();
  return (
    <div className="flex flex-col gap-3 rounded-xl border bg-accent/40 p-4">
      <div className="flex items-start gap-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-background text-sage-text">
          <MapPin className="size-5" aria-hidden />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-semibold">Appear on the map?</p>
          <p className="text-sm text-muted-foreground">
            Let nearby people discover your profile. Only an{" "}
            <span className="font-medium text-foreground">approximate</span>{" "}
            location is shared — never your exact position — and you can turn it
            off anytime.
          </p>
        </div>
      </div>
      <Button
        className="h-11 gap-2 sm:self-start"
        disabled={busy}
        onClick={() => void enable()}
      >
        <LocateFixed className="size-4" aria-hidden />
        {busy ? "Sharing…" : "Share location"}
      </Button>
    </div>
  );
}

export function MapView() {
  const router = useRouter();
  const coords = useGeoStore((s) => s.coords);
  const status = useGeoStore((s) => s.status);
  const radiusKm = useGeoStore((s) => s.radiusKm);
  const requestLocation = useGeoStore((s) => s.requestLocation);
  const setRadiusKm = useGeoStore((s) => s.setRadiusKm);
  const lowData = useSettingsStore((s) => s.lowData);
  const shareLocation = useAuthStore(
    (s) => s.activeProfile?.share_location === true,
  );
  const upsertConversation = useMessagesStore((s) => s.upsertConversation);

  const [draftRadius, setDraftRadius] = useState(radiusKm);
  const [users, setUsers] = useState<NearbyUser[]>([]);
  const [fetching, setFetching] = useState(false);
  const [presentUlids, setPresentUlids] = useState<ReadonlySet<string>>(
    new Set(),
  );
  const [selected, setSelected] = useState<NearbyUser | null>(null);
  const [mapLoaded, setMapLoaded] = useState(!lowData);
  const [dmBusy, setDmBusy] = useState(false);
  const seqRef = useRef(0);

  const showMap = Boolean(coords) && mapLoaded;

  // Presence channel: everyone in the same geohash-4 cell as the viewer.
  const geohash = coords ? geohashEncode(coords.lat, coords.lng, 4) : null;
  useEchoPresence(
    showMap && geohash ? `nearby.${geohash}` : null,
    {},
    {
      onHere: (members) =>
        setPresentUlids(
          new Set(members.map(memberUlid).filter((x): x is string => !!x)),
        ),
      onJoining: (member) => {
        const id = memberUlid(member);
        if (id)
          setPresentUlids((prev) => new Set(prev).add(id));
      },
      onLeaving: (member) => {
        const id = memberUlid(member);
        if (!id) return;
        setPresentUlids((prev) => {
          const next = new Set(prev);
          next.delete(id);
          return next;
        });
      },
    },
  );

  // Fetch nearby users whenever the map is active and coords/radius change.
  // State writes stay in async callbacks (never in the effect body).
  useEffect(() => {
    if (!showMap || !coords) return;
    const seq = ++seqRef.current;
    const timer = window.setTimeout(() => {
      setFetching(true);
      api
        .get<{ data: NearbyUser[] }>(
          `/api/v1/geo/nearby-users?lat=${coords.lat.toFixed(5)}&lng=${coords.lng.toFixed(5)}&radius_km=${radiusKm}`,
        )
        .then((res) => {
          if (seqRef.current !== seq) return;
          setUsers(res.data);
          setFetching(false);
        })
        .catch(() => {
          if (seqRef.current !== seq) return;
          setUsers([]);
          setFetching(false);
        });
    }, 0);
    return () => window.clearTimeout(timer);
  }, [showMap, coords, radiusKm]);

  async function messageUser(user: NearbyUser) {
    if (dmBusy) return;
    setDmBusy(true);
    try {
      const res = await api.post<{ data: Conversation }>(
        "/api/v1/conversations",
        { kind: "dm", profile_ulid: user.ulid },
      );
      upsertConversation(res.data);
      setSelected(null);
      router.push(`/messages/${res.data.ulid}`);
    } catch (error) {
      setDmBusy(false);
      if (error instanceof ApiError && error.status === 403) {
        toast.error(`@${user.handle} isn't accepting messages right now`);
      } else {
        toast.error(
          error instanceof ApiError ? error.message : "Couldn't start the chat",
        );
      }
    }
  }

  const banner = !shareLocation ? <OptInBanner /> : null;

  // --- No location yet: reuse the nearby-feed request flow. ----------------
  if (!coords) {
    return (
      <div className="flex flex-col gap-4">
        {banner}
        {status === "requesting" ? (
          <Skeleton className="h-[50vh] w-full rounded-xl" />
        ) : status === "denied" ? (
          <EmptyState
            icon={MapPinOff}
            title="Location access denied"
            description="The map needs your location to show who's around you. Allow location access in your browser settings, then try again."
          >
            <Button variant="outline" className="mt-2 h-11" onClick={requestLocation}>
              Try again
            </Button>
          </EmptyState>
        ) : status === "unavailable" ? (
          <EmptyState
            icon={MapPinOff}
            title="Location unavailable"
            description="We couldn't get your location right now. Check your device's location settings and try again."
          >
            <Button variant="outline" className="mt-2 h-11" onClick={requestLocation}>
              Try again
            </Button>
          </EmptyState>
        ) : (
          <EmptyState
            icon={MapPin}
            title="See who's nearby"
            description="Share your location to explore people and businesses around you on the map. It's only kept for this session."
          >
            <Button className="mt-2 h-11 gap-2" onClick={requestLocation}>
              <LocateFixed className="size-4" aria-hidden />
              Share location
            </Button>
          </EmptyState>
        )}
      </div>
    );
  }

  // --- Low-data mode: don't auto-load the map. -----------------------------
  if (!mapLoaded) {
    return (
      <div className="flex flex-col gap-4">
        {banner}
        <EmptyState
          icon={MapIcon}
          title="Map paused to save data"
          description="Data saver is on, so the map won't load automatically. Load it when you're ready."
        >
          <Button className="mt-2 h-11 gap-2" onClick={() => setMapLoaded(true)}>
            <MapIcon className="size-4" aria-hidden />
            Load map
          </Button>
        </EmptyState>
      </div>
    );
  }

  const applied = draftRadius === radiusKm;

  return (
    <div className="flex flex-col gap-3">
      {banner}

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

      <div className="relative h-[calc(100dvh-14rem)] min-h-[380px] w-full overflow-hidden rounded-xl border">
        <NearbyMap
          center={[coords.lat, coords.lng]}
          zoom={zoomForRadius(radiusKm)}
          users={users}
          presentUlids={presentUlids}
          selectedUlid={selected?.ulid ?? null}
          onSelect={setSelected}
        />
        <div className="pointer-events-none absolute top-2 left-2 z-[1] rounded-full bg-background/90 px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur tabular-nums">
          {fetching
            ? "Finding people…"
            : `${users.length} ${users.length === 1 ? "person" : "people"} within ${radiusKm} km`}
        </div>
      </div>

      <Drawer
        open={selected !== null}
        onOpenChange={(open) => {
          if (!open) setSelected(null);
        }}
      >
        <DrawerContent>
          {selected ? (
            <div className="mx-auto w-full max-w-md">
              <DrawerHeader className="flex flex-row items-center gap-3 text-left">
                <ProfileAvatar profile={selected} className="size-14" />
                <div className="min-w-0 flex-1">
                  <DrawerTitle className="flex items-center gap-2 truncate">
                    {selected.name}
                    {findRankBadge(selected) ? (
                      <RankChip
                        badge={findRankBadge(selected)!}
                        className="shrink-0"
                      />
                    ) : null}
                  </DrawerTitle>
                  <DrawerDescription className="truncate">
                    @{selected.handle}
                  </DrawerDescription>
                  <p className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground tabular-nums">
                    <span>{selected.distance_km.toFixed(1)} km away</span>
                    {presentUlids.has(selected.ulid) ? (
                      <span className="flex items-center gap-1 font-medium text-sage-text">
                        <span className="size-1.5 rounded-full bg-sage" />
                        Active now
                      </span>
                    ) : null}
                  </p>
                </div>
              </DrawerHeader>
              <div className="flex gap-2 px-4 pb-6">
                <Button asChild variant="outline" className="h-11 flex-1 gap-2">
                  <Link href={`/${selected.handle}`} onClick={() => setSelected(null)}>
                    <UserRound className="size-4" aria-hidden />
                    View profile
                  </Link>
                </Button>
                <Button
                  className="h-11 flex-1 gap-2"
                  disabled={dmBusy}
                  onClick={() => void messageUser(selected)}
                >
                  <MessageCircle className="size-4" aria-hidden />
                  Message
                </Button>
              </div>
            </div>
          ) : null}
        </DrawerContent>
      </Drawer>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
        <ShieldCheck className="size-3.5" aria-hidden />
        Locations are approximate and privacy-rounded.
      </p>
    </div>
  );
}
