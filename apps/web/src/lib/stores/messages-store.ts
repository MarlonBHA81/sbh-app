import { create } from "zustand";

import type { Conversation, ConversationLastMessage } from "@/lib/api/types";

interface BumpInput {
  conversationUlid: string;
  lastMessage: ConversationLastMessage | null;
  /** Profile that sent the bump (to avoid self-incrementing unread). */
  senderProfileUlid: string | null;
  selfProfileUlid: string | null;
}

interface MessagesState {
  /** Total unread messages across conversations (nav badge). */
  unreadTotal: number;
  /** Cached conversation list (source of truth for the list page). */
  conversations: Conversation[];
  /** Cursor for the next page of the conversation list. */
  nextCursor: string | null;
  /** Whether the first page has been fetched at least once this session. */
  listLoaded: boolean;
  /** Conversation currently open in the chat view (skip unread bumps for it). */
  openUlid: string | null;

  setUnreadTotal: (count: number) => void;
  setConversations: (list: Conversation[], cursor: string | null) => void;
  appendConversations: (list: Conversation[], cursor: string | null) => void;
  /** Insert or replace a conversation at the top (e.g. after creating a DM). */
  upsertConversation: (conversation: Conversation) => void;
  setOpen: (ulid: string | null) => void;
  /** Move a bumped conversation to the top and adjust unread counters. */
  applyBump: (input: BumpInput) => void;
  /** Clear a conversation's unread count (on read) and decrement the total. */
  markConversationRead: (ulid: string) => void;
  reset: () => void;
}

export const useMessagesStore = create<MessagesState>()((set) => ({
  unreadTotal: 0,
  conversations: [],
  nextCursor: null,
  listLoaded: false,
  openUlid: null,

  setUnreadTotal: (count) => set({ unreadTotal: Math.max(0, count) }),

  setConversations: (list, cursor) =>
    set({ conversations: list, nextCursor: cursor, listLoaded: true }),

  appendConversations: (list, cursor) =>
    set((state) => {
      const seen = new Set(state.conversations.map((c) => c.ulid));
      const extra = list.filter((c) => !seen.has(c.ulid));
      return {
        conversations: [...state.conversations, ...extra],
        nextCursor: cursor,
      };
    }),

  upsertConversation: (conversation) =>
    set((state) => ({
      conversations: [
        conversation,
        ...state.conversations.filter((c) => c.ulid !== conversation.ulid),
      ],
    })),

  setOpen: (ulid) => set({ openUlid: ulid }),

  applyBump: ({
    conversationUlid,
    lastMessage,
    senderProfileUlid,
    selfProfileUlid,
  }) =>
    set((state) => {
      const isOpen = state.openUlid === conversationUlid;
      const isSelf =
        senderProfileUlid != null && senderProfileUlid === selfProfileUlid;
      // Only unopened conversations with a message from someone else count.
      const addsUnread = !isOpen && !isSelf;

      const existing = state.conversations.find(
        (c) => c.ulid === conversationUlid,
      );
      const unreadTotal = addsUnread
        ? state.unreadTotal + 1
        : state.unreadTotal;

      if (!existing) {
        // Not cached yet — bump the badge; the list page refetches on demand.
        return { unreadTotal };
      }

      const updated: Conversation = {
        ...existing,
        last_message: lastMessage ?? existing.last_message,
        unread_count: addsUnread
          ? existing.unread_count + 1
          : existing.unread_count,
      };
      return {
        unreadTotal,
        conversations: [
          updated,
          ...state.conversations.filter((c) => c.ulid !== conversationUlid),
        ],
      };
    }),

  markConversationRead: (ulid) =>
    set((state) => {
      const target = state.conversations.find((c) => c.ulid === ulid);
      const cleared = target?.unread_count ?? 0;
      return {
        unreadTotal: Math.max(0, state.unreadTotal - cleared),
        conversations: state.conversations.map((c) =>
          c.ulid === ulid ? { ...c, unread_count: 0 } : c,
        ),
      };
    }),

  reset: () =>
    set({
      unreadTotal: 0,
      conversations: [],
      nextCursor: null,
      listLoaded: false,
      openUlid: null,
    }),
}));
