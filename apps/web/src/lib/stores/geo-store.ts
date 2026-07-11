import { create } from "zustand";

const GEO_KEY = "sbh.geo";

export const DEFAULT_RADIUS_KM = 25;
export const MIN_RADIUS_KM = 1;
export const MAX_RADIUS_KM = 100;

export interface GeoCoords {
  lat: number;
  lng: number;
}

export type GeoStatus =
  | "idle"
  | "requesting"
  | "granted"
  | "denied"
  | "unavailable";

export interface GeoStore {
  coords: GeoCoords | null;
  status: GeoStatus;
  radiusKm: number;
  requestLocation: () => void;
  setRadiusKm: (radiusKm: number) => void;
}

interface PersistedGeo {
  coords: GeoCoords | null;
  radiusKm: number;
}

function readPersisted(): PersistedGeo {
  const fallback: PersistedGeo = { coords: null, radiusKm: DEFAULT_RADIUS_KM };
  if (typeof window === "undefined") return fallback;
  try {
    const raw = window.sessionStorage.getItem(GEO_KEY);
    if (!raw) return fallback;
    const parsed = JSON.parse(raw) as Partial<PersistedGeo>;
    const coords =
      parsed.coords &&
      typeof parsed.coords.lat === "number" &&
      typeof parsed.coords.lng === "number"
        ? { lat: parsed.coords.lat, lng: parsed.coords.lng }
        : null;
    const radiusKm =
      typeof parsed.radiusKm === "number"
        ? Math.min(MAX_RADIUS_KM, Math.max(MIN_RADIUS_KM, parsed.radiusKm))
        : DEFAULT_RADIUS_KM;
    return { coords, radiusKm };
  } catch {
    return fallback;
  }
}

function persist(geo: PersistedGeo): void {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.setItem(GEO_KEY, JSON.stringify(geo));
  } catch {
    // Storage unavailable — non-fatal.
  }
}

const initial = readPersisted();

/**
 * Viewer location for the Nearby feed, persisted per browsing session
 * (sessionStorage) so we don't re-prompt on every navigation.
 */
export const useGeoStore = create<GeoStore>()((set, get) => ({
  coords: initial.coords,
  status: initial.coords ? "granted" : "idle",
  radiusKm: initial.radiusKm,

  requestLocation: () => {
    if (get().status === "requesting") return;
    if (typeof navigator === "undefined" || !("geolocation" in navigator)) {
      set({ status: "unavailable" });
      return;
    }
    set({ status: "requesting" });
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coords = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };
        set({ coords, status: "granted" });
        persist({ coords, radiusKm: get().radiusKm });
      },
      (error) => {
        set({
          coords: null,
          status:
            error.code === error.PERMISSION_DENIED ? "denied" : "unavailable",
        });
      },
      { enableHighAccuracy: false, timeout: 15_000, maximumAge: 300_000 },
    );
  },

  setRadiusKm: (radiusKm) => {
    const clamped = Math.min(
      MAX_RADIUS_KM,
      Math.max(MIN_RADIUS_KM, Math.round(radiusKm)),
    );
    set({ radiusKm: clamped });
    persist({ coords: get().coords, radiusKm: clamped });
  },
}));
