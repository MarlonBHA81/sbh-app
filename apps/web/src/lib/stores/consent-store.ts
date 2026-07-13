import { create } from "zustand";
import { persist } from "zustand/middleware";

/**
 * Cookie-consent state (GDPR/POPIA). Strictly-necessary cookies always run;
 * this records the user's choice for the optional categories and whether the
 * banner has been answered.
 */
export type ConsentChoice = "accepted" | "rejected" | null;

interface ConsentState {
  /** null = not yet answered → show the banner. */
  choice: ConsentChoice;
  /** True once the persisted value has hydrated (avoids SSR flash). */
  hydrated: boolean;
  setChoice: (choice: Exclude<ConsentChoice, null>) => void;
  reopen: () => void;
}

export const useConsentStore = create<ConsentState>()(
  persist(
    (set) => ({
      choice: null,
      hydrated: false,
      setChoice: (choice) => set({ choice }),
      reopen: () => set({ choice: null }),
    }),
    {
      name: "sbh.cookie-consent",
      onRehydrateStorage: () => (state) => {
        if (state) state.hydrated = true;
      },
    },
  ),
);
