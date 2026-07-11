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
