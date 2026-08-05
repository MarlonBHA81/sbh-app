"use client";

import { useEffect } from "react";

import * as api from "@/lib/api/client";
import type {
  Conversation,
  ConversationLastMessage,
  Paginated,
} from "@/lib/api/types";
import { useEchoPrivateEvents } from "@/lib/echo";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : null;
}

function toLastMessage(value: unknown): ConversationLastMessage | null {
  const r = asRecord(value);
  if (!r || typeof r.ulid !== "string") return null;
  return {
    ulid: r.ulid,
    preview: typeof r.preview === "string" ? r.preview : "",
    sender_handle: typeof r.sender_handle === "string" ? r.sender_handle : "",
    created_at:
      typeof r.created_at === "string" ? r.created_at : new Date().toISOString(),
    deleted: Boolean(r.deleted),
  };
}

/**
 * App-wide messaging driver: boots the unread-messages badge and listens on the
 * active profile's private channel for `.ConversationBumped` broadcasts, which
 * reorder the cached list and bump the badge. Renders nothing.
 *
 * Realtime is enhancement-only; without Echo the badge still boots from the
 * REST count and the list page refetches on navigation.
 */
export function MessagesProvider() {
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const setUnreadTotal = useMessagesStore((s) => s.setUnreadTotal);
  const applyBump = useMessagesStore((s) => s.applyBump);
  const reset = useMessagesStore((s) => s.reset);

  // Boot / refresh the unread badge whenever the active profile changes.
  useEffect(() => {
    if (!activeUlid) return;
    // Switching profiles invalidates the cached list + counters.
    reset();
    let cancelled = false;
    api
      .get<{ count: number }>("/api/v1/me/unread-messages-count")
      .then((res) => {
        if (!cancelled) setUnreadTotal(res.count ?? 0);
      })
      .catch(() => {
        // Non-fatal: leave the badge as-is.
      });
    return () => {
      cancelled = true;
    };
  }, [activeUlid, setUnreadTotal, reset]);

  useEchoPrivateEvents(activeUlid ? `profile.${activeUlid}` : null, {
    ".ConversationBumped": (payload) => {
      const p = asRecord(payload);
      if (!p) return;
      const conversationUlid =
        typeof p.conversation_ulid === "string" ? p.conversation_ulid : null;
      if (!conversationUlid) return;
      const senderProfileUlid =
        typeof p.sender_profile_ulid === "string"
          ? p.sender_profile_ulid
          : null;
      const lastMessage = toLastMessage(p.last_message);

      const store = useMessagesStore.getState();
      const known = store.conversations.some((c) => c.ulid === conversationUlid);

      applyBump({
        conversationUlid,
        lastMessage,
        senderProfileUlid,
        selfProfileUlid: activeUlid,
      });

      // A bump for a conversation we haven't cached (e.g. a brand-new DM):
      // refetch the first page so it appears in the list if it's mounted.
      if (!known && store.listLoaded) {
        api
          .get<Paginated<Conversation>>("/api/v1/conversations")
          .then((res) => {
            const s = useMessagesStore.getState();
            // Preserve any unread bumps that landed since; the fresh payload
            // already reflects server-side unread counts.
            s.setConversations(res.data, res.meta.next_cursor);
          })
          .catch(() => {
            // Non-fatal: the list refetches on next navigation.
          });
      }
    },
  });

  return null;
}
