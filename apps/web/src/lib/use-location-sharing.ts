"use client";

import { useCallback, useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useGeoStore } from "@/lib/stores/geo-store";

/** Resolve the browser's current coordinates as a promise. */
function getCurrentCoords(): Promise<{ lat: number; lng: number }> {
  return new Promise((resolve, reject) => {
    if (typeof navigator === "undefined" || !("geolocation" in navigator)) {
      reject(new Error("unavailable"));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) =>
        resolve({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        }),
      (error) =>
        reject(
          new Error(
            error.code === error.PERMISSION_DENIED ? "denied" : "unavailable",
          ),
        ),
      { enableHighAccuracy: false, timeout: 15_000, maximumAge: 300_000 },
    );
  });
}

/**
 * Location sharing for the active profile: geolocate + POST to opt in, DELETE
 * to opt out. Keeps the auth-store profile (`share_location`, city/country) and
 * the geo store's coordinates in sync so the map and nearby feed reflect the
 * change immediately. Shared by the /map opt-in banner and the settings switch.
 */
export function useLocationSharing() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const updateActiveProfile = useAuthStore((s) => s.updateActiveProfile);
  const sharing = activeProfile?.share_location === true;
  const [busy, setBusy] = useState(false);

  const enable = useCallback(async (): Promise<boolean> => {
    if (busy || !activeProfile) return false;
    setBusy(true);
    try {
      const coords = await getCurrentCoords();
      // Prime the geo store so nearby/map centre on the shared location.
      useGeoStore.setState({ coords, status: "granted" });
      const res = await api.post<{ data: Profile }>(
        `/api/v1/me/profiles/${activeProfile.ulid}/location`,
        { lat: coords.lat, lng: coords.lng },
      );
      updateActiveProfile(res.data);
      toast.success("You're now visible on the map");
      return true;
    } catch (error) {
      if (error instanceof Error && error.message === "denied") {
        toast.error("Location access denied. Enable it in your browser.");
      } else if (error instanceof api.ApiError) {
        toast.error(error.message);
      } else {
        toast.error("Couldn't get your location. Try again.");
      }
      return false;
    } finally {
      setBusy(false);
    }
  }, [busy, activeProfile, updateActiveProfile]);

  const disable = useCallback(async (): Promise<boolean> => {
    if (busy || !activeProfile) return false;
    setBusy(true);
    try {
      const res = await api.del<{ data: Profile }>(
        `/api/v1/me/profiles/${activeProfile.ulid}/location`,
      );
      updateActiveProfile(res.data);
      toast.success("You're no longer shown on the map");
      return true;
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't turn off location sharing",
      );
      return false;
    } finally {
      setBusy(false);
    }
  }, [busy, activeProfile, updateActiveProfile]);

  return { sharing, busy, enable, disable };
}
