import { createStore } from "zustand/vanilla";

import * as api from "@/lib/api/client";
import type { FeatureFlags, MeResponse, Profile, User } from "@/lib/api/types";
import { useModerationStore } from "@/lib/stores/moderation-store";

const ACTIVE_PROFILE_KEY = "sbh.activeProfileId";

export type AuthStatus = "loading" | "authed" | "guest";

export interface RegisterInput {
  name: string;
  email: string;
  password: string;
  handle?: string;
}

export interface CreateBusinessProfileInput {
  name: string;
  handle: string;
  category: string;
}

export interface AuthState {
  user: User | null;
  profiles: Profile[];
  activeProfile: Profile | null;
  status: AuthStatus;
  /** Super-admin feature toggles from /me (empty until loaded). */
  features: FeatureFlags;
}

export interface AuthActions {
  fetchMe: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
  switchProfile: (ulid: string) => void;
  createBusinessProfile: (
    input: CreateBusinessProfileInput,
  ) => Promise<Profile>;
  /** Replace the active profile in place (e.g. after PATCHing it). */
  updateActiveProfile: (profile: Profile) => void;
  /** Merge keys into the current user's settings JSON (e.g. show_sensitive). */
  updateUserSettings: (settings: Record<string, unknown>) => void;
}

export type AuthStore = AuthState & AuthActions;

function readPersistedProfileId(): string | null {
  if (typeof window === "undefined") return null;
  try {
    return window.localStorage.getItem(ACTIVE_PROFILE_KEY);
  } catch {
    return null;
  }
}

function persistProfileId(ulid: string | null): void {
  if (typeof window === "undefined") return;
  try {
    if (ulid === null) window.localStorage.removeItem(ACTIVE_PROFILE_KEY);
    else window.localStorage.setItem(ACTIVE_PROFILE_KEY, ulid);
  } catch {
    // Storage unavailable (private mode etc.) — non-fatal.
  }
}

/**
 * Ask the service worker to drop the per-user API caches (session/profile,
 * feeds, notifications) so a shared device can't serve the previous user's
 * cached data after logout.
 */
function purgeUserCaches(): void {
  if (typeof navigator === "undefined") return;
  navigator.serviceWorker?.controller?.postMessage({
    type: "sbh-purge-user-cache",
  });
}

export const defaultAuthState: AuthState = {
  user: null,
  profiles: [],
  activeProfile: null,
  status: "loading",
  features: {},
};

export function createAuthStore(initialState: AuthState = defaultAuthState) {
  return createStore<AuthStore>()((set, getState) => ({
    ...initialState,

    fetchMe: async () => {
      const persisted = readPersistedProfileId();
      if (persisted) api.setActiveProfileId(persisted);
      try {
        const { user, profiles, active_profile, features } =
          await api.get<MeResponse>("/api/v1/me");
        const active =
          profiles.find((p) => p.ulid === persisted) ??
          active_profile ??
          profiles[0] ??
          null;
        api.setActiveProfileId(active?.ulid ?? null);
        persistProfileId(active?.ulid ?? null);
        set({
          user,
          profiles,
          activeProfile: active,
          status: "authed",
          features: features ?? {},
        });
      } catch {
        api.setActiveProfileId(null);
        set({
          user: null,
          profiles: [],
          activeProfile: null,
          status: "guest",
          features: {},
        });
      }
    },

    login: async (email, password) => {
      await api.post("/api/v1/auth/login", { email, password });
      await getState().fetchMe();
    },

    register: async (input) => {
      await api.post("/api/v1/auth/register", input);
      await getState().fetchMe();
    },

    logout: async () => {
      try {
        await api.post("/api/v1/auth/logout");
      } catch {
        // Even if the API call fails, drop local session state.
      }
      api.setActiveProfileId(null);
      persistProfileId(null);
      purgeUserCaches();
      useModerationStore.getState().reset();
      set({
        user: null,
        profiles: [],
        activeProfile: null,
        status: "guest",
        features: {},
      });
    },

    switchProfile: (ulid) => {
      const profile = getState().profiles.find((p) => p.ulid === ulid);
      if (!profile) return;
      api.setActiveProfileId(profile.ulid);
      persistProfileId(profile.ulid);
      // Hidden (blocked/muted) sets are per-viewing-profile — clear on switch.
      useModerationStore.getState().reset();
      set({ activeProfile: profile });
    },

    createBusinessProfile: async (input) => {
      const res = await api.post<{ data: Profile }>("/api/v1/me/profiles", {
        kind: "business",
        ...input,
      });
      const created = res.data;
      api.setActiveProfileId(created.ulid);
      persistProfileId(created.ulid);
      set((state) => ({
        profiles: [...state.profiles, created],
        activeProfile: created,
      }));
      return created;
    },

    updateActiveProfile: (profile) => {
      set((state) => ({
        activeProfile: profile,
        profiles: state.profiles.map((p) =>
          p.ulid === profile.ulid ? profile : p,
        ),
      }));
    },

    updateUserSettings: (settings) => {
      set((state) =>
        state.user
          ? {
              user: {
                ...state.user,
                settings: { ...(state.user.settings ?? {}), ...settings },
              },
            }
          : state,
      );
    },
  }));
}

export type AuthStoreApi = ReturnType<typeof createAuthStore>;
