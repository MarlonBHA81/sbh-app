import { create } from "zustand";

interface ModerationState {
  /**
   * Profile ulids the viewer has blocked or muted this session. Post lists
   * filter their items against this set so an author's posts disappear
   * immediately (no refetch) after a block/mute.
   */
  hiddenProfileUlids: Set<string>;
  /** Hide a profile's posts (on block/mute success). */
  hideProfile: (ulid: string) => void;
  /** Un-hide a profile's posts (on unblock/unmute). */
  unhideProfile: (ulid: string) => void;
  /** Clear on profile switch / logout. */
  reset: () => void;
}

export const useModerationStore = create<ModerationState>()((set) => ({
  hiddenProfileUlids: new Set<string>(),

  hideProfile: (ulid) =>
    set((state) => {
      if (state.hiddenProfileUlids.has(ulid)) return state;
      const next = new Set(state.hiddenProfileUlids);
      next.add(ulid);
      return { hiddenProfileUlids: next };
    }),

  unhideProfile: (ulid) =>
    set((state) => {
      if (!state.hiddenProfileUlids.has(ulid)) return state;
      const next = new Set(state.hiddenProfileUlids);
      next.delete(ulid);
      return { hiddenProfileUlids: next };
    }),

  reset: () => {
    set((state) =>
      state.hiddenProfileUlids.size === 0
        ? state
        : { hiddenProfileUlids: new Set<string>() },
    );
  },
}));
