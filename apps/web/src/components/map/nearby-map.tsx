"use client";

import { Map, Overlay } from "pigeon-maps";

import { ProfileAvatar } from "@/components/profile-avatar";
import type { NearbyUser } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export interface NearbyMapView {
  center: [number, number];
  zoom: number;
}

/**
 * OpenStreetMap (pigeon-maps default tiles) with a custom avatar pin overlay
 * per nearby user. Present ("active now") users get a pulsing emerald ring;
 * the selected pin is lifted and ringed. Naïve rendering — every result gets a
 * pin (fine at this scale, cluster threshold ~30).
 */
export function NearbyMap({
  center,
  zoom,
  users,
  presentUlids,
  selectedUlid,
  onSelect,
  onBoundsChanged,
}: {
  center: [number, number];
  zoom: number;
  users: NearbyUser[];
  presentUlids: ReadonlySet<string>;
  selectedUlid: string | null;
  onSelect: (user: NearbyUser) => void;
  onBoundsChanged?: (view: NearbyMapView) => void;
}) {
  return (
    <Map
      center={center}
      zoom={zoom}
      metaWheelZoom
      attributionPrefix={false}
      onBoundsChanged={({ center: c, zoom: z }) =>
        onBoundsChanged?.({ center: c, zoom: z })
      }
    >
      {/* Viewer's own position marker. */}
      <Overlay anchor={center} offset={[8, 8]}>
        <span className="relative flex size-4">
          <span className="absolute inline-flex size-full animate-ping rounded-full bg-teal/60 motion-reduce:hidden" />
          <span className="relative inline-flex size-4 rounded-full border-2 border-background bg-teal shadow" />
        </span>
      </Overlay>

      {users.map((user) => {
        const present = presentUlids.has(user.ulid);
        const selected = selectedUlid === user.ulid;
        return (
          <Overlay key={user.ulid} anchor={[user.lat, user.lng]} offset={[20, 20]}>
            <button
              type="button"
              onClick={() => onSelect(user)}
              aria-label={`${user.name}${present ? ", active now" : ""}`}
              className="relative block size-10 rounded-full outline-none"
            >
              {present ? (
                <span className="absolute inset-0 animate-ping rounded-full bg-sage/50 motion-reduce:hidden" />
              ) : null}
              <ProfileAvatar
                profile={user}
                className={cn(
                  "relative size-10 shadow-md ring-2 transition-transform",
                  present
                    ? "ring-sage"
                    : "ring-background",
                  selected &&
                    "scale-110 ring-primary ring-offset-2 ring-offset-background",
                )}
              />
            </button>
          </Overlay>
        );
      })}
    </Map>
  );
}
