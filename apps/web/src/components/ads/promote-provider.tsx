"use client";

import dynamic from "next/dynamic";
import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
} from "react";

import type { Post } from "@/lib/api/types";

// Loaded on demand — the promote flow (and recharts on the detail page) stays
// out of the initial bundle.
const PromoteSheet = dynamic(
  () => import("./promote-sheet").then((mod) => mod.PromoteSheet),
  { ssr: false },
);

interface PromoteContextValue {
  /** Open the promote flow, optionally preselecting a post to promote. */
  openPromote: (post?: Post) => void;
  /** Bumps after a campaign is created — use as a list refresh key. */
  campaignMutationCount: number;
  /** Notify campaign lists that a campaign changed (create/pause/end). */
  notifyCampaignsMutated: () => void;
}

const PromoteContext = createContext<PromoteContextValue | null>(null);

export function usePromote(): PromoteContextValue {
  const context = useContext(PromoteContext);
  if (!context) {
    throw new Error("usePromote must be used within <PromoteProvider>");
  }
  return context;
}

export function PromoteProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const [preselected, setPreselected] = useState<Post | null>(null);
  // Remount each open so the wizard resets to step 1 cleanly.
  const [instance, setInstance] = useState(0);
  const [campaignMutationCount, setCampaignMutationCount] = useState(0);

  const openPromote = useCallback((post?: Post) => {
    setPreselected(post ?? null);
    setInstance((i) => i + 1);
    setOpen(true);
  }, []);

  const notifyCampaignsMutated = useCallback(() => {
    setCampaignMutationCount((count) => count + 1);
  }, []);

  const value = useMemo(
    () => ({ openPromote, campaignMutationCount, notifyCampaignsMutated }),
    [openPromote, campaignMutationCount, notifyCampaignsMutated],
  );

  return (
    <PromoteContext.Provider value={value}>
      {children}
      {instance > 0 ? (
        <PromoteSheet
          key={instance}
          open={open}
          onOpenChange={setOpen}
          preselected={preselected}
          onCreated={notifyCampaignsMutated}
        />
      ) : null}
    </PromoteContext.Provider>
  );
}
