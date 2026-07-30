"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { useStore } from "zustand";

import {
  createAuthStore,
  type AuthStore,
  type AuthStoreApi,
} from "@/lib/stores/auth-store";

const AuthStoreContext = createContext<AuthStoreApi | undefined>(undefined);

export function AuthStoreProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  // Lazily create one store per provider instance (per request on the
  // server, once per app on the client) — no module-level global.
  const [store] = useState(() => createAuthStore());

  useEffect(() => {
    void store.getState().fetchMe();
  }, [store]);

  return (
    <AuthStoreContext.Provider value={store}>
      {children}
    </AuthStoreContext.Provider>
  );
}

export function useAuthStore<T>(selector: (store: AuthStore) => T): T {
  const store = useContext(AuthStoreContext);
  if (!store) {
    throw new Error("useAuthStore must be used within AuthStoreProvider");
  }
  return useStore(store, selector);
}

/**
 * Whether a super-admin feature flag is on. Defaults to `true` until /me has
 * loaded (and for any flag the client doesn't know about) so features never
 * flash hidden on first paint — the server is the source of truth and gates the
 * data regardless.
 */
export function useFeature(key: string): boolean {
  return useAuthStore((store) => store.features[key] ?? true);
}

/**
 * The raw store API (getState/setState) for reading fresh state imperatively —
 * e.g. the active profile right after register()/fetchMe() resolves, before a
 * re-render would surface it through the selector hook.
 */
export function useAuthStoreApi(): AuthStoreApi {
  const store = useContext(AuthStoreContext);
  if (!store) {
    throw new Error("useAuthStoreApi must be used within AuthStoreProvider");
  }
  return store;
}
