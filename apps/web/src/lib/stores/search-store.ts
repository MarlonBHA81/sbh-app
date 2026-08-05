import { create } from "zustand";

/**
 * Open/close state for the global search command palette. A single dialog is
 * rendered at the shell level; the mobile top bar and desktop sidebar triggers
 * (and the ⌘K / Ctrl-K shortcut) all flip this one flag.
 */
export interface SearchStore {
  open: boolean;
  setOpen: (open: boolean) => void;
}

export const useSearchStore = create<SearchStore>()((set) => ({
  open: false,
  setOpen: (open) => set({ open }),
}));
