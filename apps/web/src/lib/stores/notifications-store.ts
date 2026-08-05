import { create } from "zustand";

import type { AppNotification } from "@/lib/api/types";

interface NotificationsState {
  /** Unread count shown as the nav bell badge. */
  unreadCount: number;
  /**
   * Notifications received live via Echo since this session started (newest
   * first). The notifications page merges these into its paginated list.
   */
  live: AppNotification[];
  setUnreadCount: (count: number) => void;
  incrementUnread: () => void;
  clearUnread: () => void;
  /** Record a live notification and bump the badge. */
  pushLive: (notification: AppNotification) => void;
}

export const useNotificationsStore = create<NotificationsState>()((set) => ({
  unreadCount: 0,
  live: [],
  setUnreadCount: (count) => set({ unreadCount: Math.max(0, count) }),
  incrementUnread: () =>
    set((state) => ({ unreadCount: state.unreadCount + 1 })),
  clearUnread: () => set({ unreadCount: 0 }),
  pushLive: (notification) =>
    set((state) => {
      if (state.live.some((n) => n.id === notification.id)) return state;
      return {
        live: [notification, ...state.live],
        unreadCount: state.unreadCount + 1,
      };
    }),
}));
