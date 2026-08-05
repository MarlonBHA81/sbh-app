import { create } from "zustand";

import type { RankSummary } from "@/lib/api/types";

interface GamificationState {
  /**
   * The rank whose unlock celebration should be shown, or null when none is
   * pending. Set from the notifications pipeline when a `rank_unlocked`
   * notification arrives; the GamificationProvider renders the dialog.
   */
  pendingRank: RankSummary | null;
  celebrateRank: (rank: RankSummary) => void;
  dismissRank: () => void;
}

export const useGamificationStore = create<GamificationState>()((set) => ({
  pendingRank: null,
  celebrateRank: (rank) => set({ pendingRank: rank }),
  dismissRank: () => set({ pendingRank: null }),
}));
