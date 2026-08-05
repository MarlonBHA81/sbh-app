import { create } from "zustand";

const SETTINGS_KEY = "sbh.settings";

interface PersistedSettings {
  /** Low data mode: thumbnails only, smaller feed pages, no autoplay. */
  lowData: boolean;
  /** Future flag: whether videos may autoplay (always off in low data). */
  videoAutoplay: boolean;
}

export interface SettingsStore extends PersistedSettings {
  setLowData: (lowData: boolean) => void;
  setVideoAutoplay: (videoAutoplay: boolean) => void;
}

const defaults: PersistedSettings = {
  lowData: false,
  videoAutoplay: true,
};

function readPersisted(): Partial<PersistedSettings> {
  if (typeof window === "undefined") return {};
  try {
    const raw = window.localStorage.getItem(SETTINGS_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw) as Partial<PersistedSettings>;
    return {
      ...(typeof parsed.lowData === "boolean" && { lowData: parsed.lowData }),
      ...(typeof parsed.videoAutoplay === "boolean" && {
        videoAutoplay: parsed.videoAutoplay,
      }),
    };
  } catch {
    return {};
  }
}

function persist(settings: PersistedSettings): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
  } catch {
    // Storage unavailable (private mode etc.) — non-fatal.
  }
}

/**
 * User preferences persisted to localStorage. Only mutated on the client;
 * the server render always sees the defaults (the app shell is client-gated,
 * so there is no hydration mismatch).
 */
export const useSettingsStore = create<SettingsStore>()((set, get) => ({
  ...defaults,
  ...readPersisted(),

  setLowData: (lowData) => {
    set({ lowData });
    persist({ lowData, videoAutoplay: get().videoAutoplay });
  },

  setVideoAutoplay: (videoAutoplay) => {
    set({ videoAutoplay });
    persist({ lowData: get().lowData, videoAutoplay });
  },
}));

/** Effective autoplay: the user flag, force-disabled by low data mode. */
export function selectVideoAutoplay(state: SettingsStore): boolean {
  return state.videoAutoplay && !state.lowData;
}
