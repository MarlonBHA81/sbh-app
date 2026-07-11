"use client";

import { useRouter } from "next/navigation";
import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from "react";
import { toast } from "sonner";

import { ChatHeader } from "@/components/messages/chat-header";
import { EditGroupDialog } from "@/components/messages/edit-group-dialog";
import { MembersSheet } from "@/components/messages/members-sheet";
import { MessageBubble } from "@/components/messages/message-bubble";
import { MessageComposer } from "@/components/messages/message-composer";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type {
  Conversation,
  Media,
  MessageReaction,
  Paginated,
  ProfileLite,
} from "@/lib/api/types";
import { useEchoPresence } from "@/lib/echo";
import { dayKey, dayLabel, shouldGroup, toMessage } from "@/lib/messages";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";

import type { ChatMessage } from "./chat-types";

/** Best-effort extraction of a profile ulid from a presence member payload. */
function memberUlid(member: unknown): string | null {
  if (typeof member === "string") return member;
  const r = member as Record<string, unknown> | null;
  if (!r || typeof r !== "object") return null;
  for (const key of ["ulid", "profile_ulid", "id"]) {
    if (typeof r[key] === "string") return r[key] as string;
  }
  const info = r.info as Record<string, unknown> | undefined;
  if (info && typeof info.ulid === "string") return info.ulid;
  return null;
}

/** Toggle a single emoji reaction on a reactions array (optimistic). */
function toggleReaction(
  reactions: MessageReaction[],
  emoji: string,
): MessageReaction[] {
  const existing = reactions.find((r) => r.emoji === emoji);
  if (!existing) {
    return [...reactions, { emoji, count: 1, reacted_by_me: true }];
  }
  if (existing.reacted_by_me) {
    const count = existing.count - 1;
    return count <= 0
      ? reactions.filter((r) => r.emoji !== emoji)
      : reactions.map((r) =>
          r.emoji === emoji ? { ...r, count, reacted_by_me: false } : r,
        );
  }
  return reactions.map((r) =>
    r.emoji === emoji
      ? { ...r, count: r.count + 1, reacted_by_me: true }
      : r,
  );
}

export function ChatView({ ulid }: { ulid: string }) {
  const router = useRouter();
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const selfUlid = activeProfile?.ulid ?? null;
  const setOpen = useMessagesStore((s) => s.setOpen);
  const markConversationRead = useMessagesStore((s) => s.markConversationRead);

  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">("loading");
  const [loadingEarlier, setLoadingEarlier] = useState(false);

  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const [membersOpen, setMembersOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [rulesOpen, setRulesOpen] = useState(false);

  const [presentUlids, setPresentUlids] = useState<ReadonlySet<string>>(
    () => new Set(),
  );
  const [readReceipts, setReadReceipts] = useState<Record<string, string>>({});
  const [typingName, setTypingName] = useState<string | null>(null);

  const containerRef = useRef<HTMLDivElement | null>(null);
  const atBottomRef = useRef(true);
  const prependHeightRef = useRef<number | null>(null);
  const stickRef = useRef(true);
  const lastReadSentRef = useRef<string | null>(null);
  const lastTypingAtRef = useRef(0);
  const typingTimerRef = useRef<number | null>(null);
  const tempCounter = useRef(0);

  const selfLite: ProfileLite | null = activeProfile
    ? {
        ulid: activeProfile.ulid,
        handle: activeProfile.handle,
        name: activeProfile.name,
        avatar_url: activeProfile.avatar_url,
      }
    : null;

  // --- Read receipt POST --------------------------------------------------
  const markRead = useCallback(() => {
    const newest = [...messages].reverse().find((m) => !m.pending);
    if (!newest) return;
    if (lastReadSentRef.current === newest.ulid) return;
    lastReadSentRef.current = newest.ulid;
    markConversationRead(ulid);
    api
      .post(`/api/v1/conversations/${ulid}/read`, { message_ulid: newest.ulid })
      .catch(() => {
        // Best-effort; re-attempts on the next trigger.
      });
  }, [messages, ulid, markConversationRead]);

  // --- Initial load -------------------------------------------------------
  useEffect(() => {
    setOpen(ulid);
    let cancelled = false;
    Promise.all([
      api.get<{ data: Conversation }>(`/api/v1/conversations/${ulid}`),
      api.get<Paginated<ChatMessage>>(
        `/api/v1/conversations/${ulid}/messages`,
      ),
    ])
      .then(([conv, msgs]) => {
        if (cancelled) return;
        setConversation(conv.data);
        // API returns newest-first; render oldest→newest.
        setMessages([...msgs.data].reverse());
        setCursor(msgs.meta.next_cursor);
        stickRef.current = true;
        setPhase("loaded");
      })
      .catch(() => {
        if (!cancelled) setPhase("error");
      });
    return () => {
      cancelled = true;
      setOpen(null);
    };
  }, [ulid, setOpen]);

  // Clear unread + POST read once the thread is loaded / on window focus.
  useEffect(() => {
    if (phase !== "loaded") return;
    markRead();
    const onFocus = () => markRead();
    window.addEventListener("focus", onFocus);
    return () => window.removeEventListener("focus", onFocus);
  }, [phase, markRead]);

  // --- Scroll management --------------------------------------------------
  useLayoutEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    if (prependHeightRef.current != null) {
      // Preserve viewport position after prepending older history.
      el.scrollTop = el.scrollHeight - prependHeightRef.current;
      prependHeightRef.current = null;
      return;
    }
    if (stickRef.current || atBottomRef.current) {
      el.scrollTop = el.scrollHeight;
      stickRef.current = false;
    }
  }, [messages]);

  function onScroll() {
    const el = containerRef.current;
    if (!el) return;
    atBottomRef.current =
      el.scrollHeight - el.scrollTop - el.clientHeight < 80;
  }

  async function loadEarlier() {
    if (!cursor || loadingEarlier) return;
    setLoadingEarlier(true);
    const el = containerRef.current;
    prependHeightRef.current = el ? el.scrollHeight : null;
    try {
      const res = await api.get<Paginated<ChatMessage>>(
        `/api/v1/conversations/${ulid}/messages?cursor=${encodeURIComponent(cursor)}`,
      );
      setMessages((prev) => {
        const seen = new Set(prev.map((m) => m.ulid));
        const older = [...res.data]
          .reverse()
          .filter((m) => !seen.has(m.ulid));
        return [...older, ...prev];
      });
      setCursor(res.meta.next_cursor);
    } catch {
      prependHeightRef.current = null;
    } finally {
      setLoadingEarlier(false);
    }
  }

  // --- Realtime (presence channel conversation.{ulid}) --------------------
  const { whisper } = useEchoPresence(
    phase === "loaded" ? `conversation.${ulid}` : null,
    {
      ".MessageSent": (payload) => {
        const incoming = toMessage(payload);
        if (!incoming) return;
        setMessages((prev) => {
          if (prev.some((m) => m.ulid === incoming.ulid)) {
            return prev.map((m) => (m.ulid === incoming.ulid ? incoming : m));
          }
          // Reconcile our own optimistic send if the POST hasn't resolved yet.
          if (incoming.profile.ulid === selfUlid) {
            const idx = prev.findIndex(
              (m) => m.pending === "sending" && m.body === incoming.body,
            );
            if (idx !== -1) {
              const next = [...prev];
              next[idx] = incoming;
              return next;
            }
          }
          return [...prev, incoming];
        });
        if (incoming.profile.ulid !== selfUlid && document.hasFocus()) {
          // A new message we can see — refresh the read marker shortly after.
          window.setTimeout(markRead, 0);
        }
      },
      ".MessageDeleted": (payload) => {
        const p = payload as Record<string, unknown> | null;
        const deletedUlid =
          p && typeof p.message_ulid === "string" ? p.message_ulid : null;
        if (!deletedUlid) return;
        setMessages((prev) =>
          prev.map((m) =>
            m.ulid === deletedUlid
              ? { ...m, deleted: true, body: null, media: [] }
              : m,
          ),
        );
      },
      ".MessageReacted": (payload) => {
        const p = payload as Record<string, unknown> | null;
        const targetUlid =
          p && typeof p.message_ulid === "string" ? p.message_ulid : null;
        const reactions = p && Array.isArray(p.reactions) ? p.reactions : null;
        if (!targetUlid || !reactions) return;
        setMessages((prev) =>
          prev.map((m) =>
            m.ulid === targetUlid
              ? { ...m, reactions: reactions as MessageReaction[] }
              : m,
          ),
        );
      },
      ".ReadReceipt": (payload) => {
        const p = payload as Record<string, unknown> | null;
        const pid =
          p && typeof p.profile_ulid === "string" ? p.profile_ulid : null;
        const mid =
          p && typeof p.message_ulid === "string" ? p.message_ulid : null;
        if (!pid || !mid) return;
        setReadReceipts((prev) => ({ ...prev, [pid]: mid }));
      },
    },
    {
      onHere: (members) =>
        setPresentUlids(
          new Set(members.map(memberUlid).filter((x): x is string => !!x)),
        ),
      onJoining: (member) => {
        const id = memberUlid(member);
        if (id) setPresentUlids((prev) => new Set(prev).add(id));
      },
      onLeaving: (member) => {
        const id = memberUlid(member);
        if (id)
          setPresentUlids((prev) => {
            const next = new Set(prev);
            next.delete(id);
            return next;
          });
      },
      whispers: {
        typing: (data) => {
          const d = data as Record<string, unknown> | null;
          const name =
            d && typeof d.name === "string" ? d.name : "Someone";
          setTypingName(name);
          if (typingTimerRef.current) window.clearTimeout(typingTimerRef.current);
          typingTimerRef.current = window.setTimeout(
            () => setTypingName(null),
            4000,
          );
        },
      },
    },
  );

  function handleTyping() {
    const now = Date.now();
    if (now - lastTypingAtRef.current < 2000) return;
    lastTypingAtRef.current = now;
    if (activeProfile) {
      whisper("typing", {
        handle: activeProfile.handle,
        name: activeProfile.name,
      });
    }
  }

  // --- Sending ------------------------------------------------------------
  async function sendMessage(
    body: string,
    media: Media[],
    reply: ChatMessage | null,
    existingTempId?: string,
  ) {
    if (!selfLite) return;
    const tempId = existingTempId ?? `temp-${++tempCounter.current}-${Date.now()}`;
    const optimistic: ChatMessage = {
      ulid: tempId,
      tempId,
      pending: "sending",
      body: body || null,
      reply_to: reply
        ? {
            ulid: reply.ulid,
            body: reply.body,
            sender: reply.profile,
          }
        : null,
      reactions: [],
      media,
      profile: selfLite,
      created_at: new Date().toISOString(),
    };

    stickRef.current = true;
    setMessages((prev) =>
      existingTempId
        ? prev.map((m) =>
            m.tempId === existingTempId
              ? { ...optimistic, created_at: m.created_at }
              : m,
          )
        : [...prev, optimistic],
    );

    try {
      const res = await api.post<{ data: ChatMessage }>(
        `/api/v1/conversations/${ulid}/messages`,
        {
          ...(body ? { body } : {}),
          ...(media.length ? { media_ids: media.map((m) => m.ulid) } : {}),
          ...(reply ? { reply_to_ulid: reply.ulid } : {}),
        },
      );
      const real = res.data;
      setMessages((prev) => {
        // Replace the optimistic entry; skip if the echo already delivered it.
        if (prev.some((m) => m.ulid === real.ulid && m.tempId !== tempId)) {
          return prev.filter((m) => m.tempId !== tempId);
        }
        return prev.map((m) => (m.tempId === tempId ? real : m));
      });
    } catch {
      setMessages((prev) =>
        prev.map((m) =>
          m.tempId === tempId ? { ...m, pending: "failed" } : m,
        ),
      );
    }
  }

  function handleSend(body: string, media: Media[]) {
    const reply = replyTo;
    setReplyTo(null);
    void sendMessage(body, media, reply);
  }

  function handleRetry(message: ChatMessage) {
    const reply = message.reply_to
      ? messages.find((m) => m.ulid === message.reply_to?.ulid) ?? null
      : null;
    setMessages((prev) =>
      prev.map((m) =>
        m.tempId === message.tempId ? { ...m, pending: "sending" } : m,
      ),
    );
    void sendMessage(message.body ?? "", message.media, reply, message.tempId);
  }

  // --- Reactions / delete -------------------------------------------------
  function handleReact(message: ChatMessage, emoji: string) {
    if (message.pending) return;
    const wasReacted =
      message.reactions.find((r) => r.emoji === emoji)?.reacted_by_me ?? false;
    setMessages((prev) =>
      prev.map((m) =>
        m.ulid === message.ulid
          ? { ...m, reactions: toggleReaction(m.reactions, emoji) }
          : m,
      ),
    );
    const call = wasReacted
      ? api.del(`/api/v1/messages/${message.ulid}/reactions`, { emoji })
      : api.post(`/api/v1/messages/${message.ulid}/reactions`, { emoji });
    call.catch(() => {
      // Revert on failure (the echo event would otherwise reconcile).
      setMessages((prev) =>
        prev.map((m) =>
          m.ulid === message.ulid
            ? { ...m, reactions: toggleReaction(m.reactions, emoji) }
            : m,
        ),
      );
      toast.error("Couldn't update reaction");
    });
  }

  function handleDelete(message: ChatMessage) {
    if (message.pending) {
      // Drop an unsent/failed optimistic message locally.
      setMessages((prev) => prev.filter((m) => m.tempId !== message.tempId));
      return;
    }
    const snapshot = message;
    setMessages((prev) =>
      prev.map((m) =>
        m.ulid === message.ulid
          ? { ...m, deleted: true, body: null, media: [] }
          : m,
      ),
    );
    api.del(`/api/v1/messages/${message.ulid}`).catch(() => {
      setMessages((prev) =>
        prev.map((m) => (m.ulid === message.ulid ? snapshot : m)),
      );
      toast.error("Couldn't delete message");
    });
  }

  function jumpToReply(targetUlid: string) {
    const el = document.getElementById(`msg-${targetUlid}`);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  async function leaveConversation() {
    if (!selfUlid) return;
    try {
      await api.del(
        `/api/v1/conversations/${ulid}/participants/${selfUlid}`,
      );
      useMessagesStore.setState((s) => ({
        conversations: s.conversations.filter((c) => c.ulid !== ulid),
      }));
      toast.success("You left the conversation");
      router.push("/messages");
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't leave",
      );
    }
  }

  // --- Derived: "Seen" marker (DM/group: last own msg read by others) -----
  const otherReadUlids = Object.entries(readReceipts)
    .filter(([pid]) => pid !== selfUlid)
    .map(([, mid]) => mid)
    .sort();
  const maxReadUlid = otherReadUlids.at(-1) ?? null;
  let seenUlid: string | null = null;
  if (maxReadUlid) {
    for (const m of messages) {
      if (m.profile.ulid === selfUlid && !m.pending && m.ulid <= maxReadUlid) {
        seenUlid = m.ulid;
      }
    }
  }

  if (phase === "error") {
    return (
      <div className="flex min-h-[60dvh] flex-col items-center justify-center gap-3 text-center">
        <p className="text-sm text-muted-foreground">
          This conversation couldn&apos;t be loaded.
        </p>
        <button
          type="button"
          onClick={() => router.push("/messages")}
          className="text-sm font-medium text-primary underline-offset-4 hover:underline"
        >
          Back to messages
        </button>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-20 flex flex-col bg-background md:static md:inset-auto md:z-auto md:h-[calc(100dvh-2rem)]">
      {conversation ? (
        <ChatHeader
          conversation={conversation}
          selfUlid={selfUlid}
          online={
            conversation.kind === "dm" &&
            conversation.participants.some(
              (p) =>
                p.profile.ulid !== selfUlid &&
                presentUlids.has(p.profile.ulid),
            )
          }
          onOpenMembers={() => setMembersOpen(true)}
          onEditGroup={() => setEditOpen(true)}
          onShowRules={() => setRulesOpen(true)}
          onLeave={() => void leaveConversation()}
        />
      ) : (
        <div className="flex h-14 items-center gap-2 border-b px-4">
          <Skeleton className="size-9 rounded-full" />
          <Skeleton className="h-4 w-32" />
        </div>
      )}

      <div
        ref={containerRef}
        onScroll={onScroll}
        className="flex-1 overflow-y-auto overscroll-contain px-3 py-2"
      >
        {phase === "loading" ? (
          <div className="flex flex-col gap-3 py-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton
                key={i}
                className={`h-10 rounded-2xl ${i % 2 ? "w-40 self-end" : "w-52"}`}
              />
            ))}
          </div>
        ) : null}

        {phase === "loaded" ? (
          <>
            {cursor ? (
              <div className="flex justify-center py-2">
                <button
                  type="button"
                  onClick={() => void loadEarlier()}
                  disabled={loadingEarlier}
                  className="text-xs font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
                >
                  {loadingEarlier ? "Loading…" : "Load earlier"}
                </button>
              </div>
            ) : null}

            {messages.length === 0 ? (
              <p className="py-10 text-center text-sm text-muted-foreground">
                No messages yet. Say hello.
              </p>
            ) : null}

            {messages.map((message, index) => {
              const prev = index > 0 ? messages[index - 1] : null;
              const newDay =
                !prev ||
                dayKey(prev.created_at) !== dayKey(message.created_at);
              const grouped = !newDay && shouldGroup(prev, message);
              return (
                <div key={message.tempId ?? message.ulid}>
                  {newDay ? (
                    <div className="my-3 flex justify-center">
                      <span className="rounded-full bg-muted px-3 py-0.5 text-[11px] font-medium text-muted-foreground">
                        {dayLabel(message.created_at)}
                      </span>
                    </div>
                  ) : null}
                  <MessageBubble
                    message={message}
                    own={message.profile.ulid === selfUlid}
                    grouped={grouped}
                    showSeen={message.ulid === seenUlid}
                    onReact={handleReact}
                    onReply={setReplyTo}
                    onDelete={handleDelete}
                    onRetry={handleRetry}
                    onJumpToReply={jumpToReply}
                  />
                </div>
              );
            })}
          </>
        ) : null}
      </div>

      {typingName ? (
        <div className="px-4 pb-1 text-xs text-muted-foreground">
          {typingName} is typing…
        </div>
      ) : null}

      {conversation ? (
        <MessageComposer
          replyTo={replyTo}
          onCancelReply={() => setReplyTo(null)}
          onSend={handleSend}
          onTyping={handleTyping}
        />
      ) : null}

      {conversation ? (
        <MembersSheet
          conversation={conversation}
          selfUlid={selfUlid}
          open={membersOpen}
          onOpenChange={setMembersOpen}
          onConversationChange={setConversation}
        />
      ) : null}

      {conversation && conversation.kind === "group" ? (
        <EditGroupDialog
          conversation={conversation}
          open={editOpen}
          onOpenChange={setEditOpen}
          onUpdated={setConversation}
        />
      ) : null}

      {conversation?.rules ? (
        <Dialog open={rulesOpen} onOpenChange={setRulesOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Group rules</DialogTitle>
              <DialogDescription className="sr-only">
                The rules for this group conversation.
              </DialogDescription>
            </DialogHeader>
            <p className="text-sm whitespace-pre-wrap text-foreground">
              {conversation.rules}
            </p>
          </DialogContent>
        </Dialog>
      ) : null}
    </div>
  );
}
