"use client";

import { Send, SmilePlus } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as api from "@/lib/api/client";
import { useEchoPresence } from "@/lib/echo";
import type { Message, Paginated } from "@/lib/api/types";
import { toMessage } from "@/lib/messages";

/** A quick-reaction on chat messages + the floating live-reaction bar. */
const REACTIONS = ["👏", "❤️", "😂", "🎉", "👍", "😮"];

interface FloatingReaction {
  id: number;
  emoji: string;
  left: number;
}

/**
 * Live room chat (ask #4). Reuses the existing group-conversation message API +
 * Reverb presence channel; adds ephemeral Zoom-style floating reactions.
 */
export function RoomChat({
  conversationUlid,
  masterclassUlid,
}: {
  conversationUlid: string;
  masterclassUlid: string;
}) {
  const [messages, setMessages] = useState<Message[]>([]);
  const [draft, setDraft] = useState("");
  const [sending, setSending] = useState(false);
  const [floats, setFloats] = useState<FloatingReaction[]>([]);
  const listRef = useRef<HTMLDivElement | null>(null);
  const floatId = useRef(0);

  const addFloat = useCallback((emoji: string) => {
    const id = floatId.current++;
    setFloats((prev) => [...prev, { id, emoji, left: 10 + Math.random() * 70 }]);
    setTimeout(() => setFloats((prev) => prev.filter((f) => f.id !== id)), 2600);
  }, []);

  const upsert = useCallback((msg: Message) => {
    setMessages((prev) =>
      prev.some((m) => m.ulid === msg.ulid)
        ? prev.map((m) => (m.ulid === msg.ulid ? msg : m))
        : [...prev, msg],
    );
  }, []);

  // Initial load (newest-first from API → oldest-first for display).
  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Message>>(
        `/api/v1/conversations/${conversationUlid}/messages`,
      )
      .then((res) => {
        if (!cancelled) setMessages([...res.data].reverse());
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [conversationUlid]);

  // Realtime: new messages, reaction changes, deletes, and live reactions.
  useEchoPresence(`conversation.${conversationUlid}`, {
    ".MessageSent": (payload: unknown) => {
      const msg = toMessage((payload as { message: unknown }).message);
      if (msg) upsert(msg);
    },
    ".MessageReacted": (payload: unknown) => {
      const p = payload as { message_ulid: string; reactions: Message["reactions"] };
      setMessages((prev) =>
        prev.map((m) =>
          m.ulid === p.message_ulid ? { ...m, reactions: p.reactions } : m,
        ),
      );
    },
    ".MessageDeleted": (payload: unknown) => {
      const p = payload as { message_ulid: string };
      setMessages((prev) =>
        prev.map((m) =>
          m.ulid === p.message_ulid
            ? { ...m, deleted: true, body: null }
            : m,
        ),
      );
    },
    ".LiveReaction": (payload: unknown) => {
      const p = payload as { emoji?: string };
      if (p.emoji) addFloat(p.emoji);
    },
  });

  // Keep pinned to the newest message.
  useEffect(() => {
    const el = listRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, [messages]);

  async function send() {
    const body = draft.trim();
    if (body === "" || sending) return;
    setSending(true);
    setDraft("");
    try {
      const res = await api.post<{ data: Message }>(
        `/api/v1/conversations/${conversationUlid}/messages`,
        { body },
      );
      upsert(res.data); // echo will also arrive; upsert dedupes by ulid.
    } catch {
      setDraft(body);
    } finally {
      setSending(false);
    }
  }

  async function toggleReaction(msg: Message, emoji: string) {
    const mine = msg.reactions.find((r) => r.emoji === emoji)?.reacted_by_me;
    try {
      if (mine) {
        await api.del(`/api/v1/messages/${msg.ulid}/reactions`, { emoji });
      } else {
        await api.post(`/api/v1/messages/${msg.ulid}/reactions`, { emoji });
      }
    } catch {
      /* the broadcast reconciles state; ignore */
    }
  }

  async function fireLive(emoji: string) {
    addFloat(emoji); // instant local feedback
    try {
      await api.post(`/api/v1/masterclasses/${masterclassUlid}/live/react`, {
        emoji,
      });
    } catch {
      /* fire-and-forget */
    }
  }

  return (
    <div className="relative flex h-[28rem] flex-col rounded-(--radius-card) border border-warmgray bg-card shadow-card">
      {/* Floating live reactions overlay. */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        {floats.map((f) => (
          <span
            key={f.id}
            className="absolute bottom-16 animate-[sbh-float-up_2.6s_ease-out_forwards] text-2xl"
            style={{ left: `${f.left}%` }}
          >
            {f.emoji}
          </span>
        ))}
      </div>

      <div className="flex items-center justify-between border-b border-warmgray px-3 py-2">
        <span className="text-[13px] font-semibold text-text-primary">
          Room chat
        </span>
        <div className="flex gap-1">
          {REACTIONS.map((e) => (
            <button
              key={e}
              type="button"
              aria-label={`React ${e}`}
              onClick={() => void fireLive(e)}
              className="rounded-full px-1 text-base transition-transform hover:scale-125"
            >
              {e}
            </button>
          ))}
        </div>
      </div>

      <div ref={listRef} className="flex-1 space-y-2 overflow-y-auto p-3">
        {messages.length === 0 ? (
          <p className="pt-8 text-center text-[13px] text-text-secondary">
            Say hello 👋
          </p>
        ) : (
          messages.map((m) => (
            <ChatRow key={m.ulid} message={m} onReact={toggleReaction} />
          ))
        )}
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          void send();
        }}
        className="flex items-center gap-2 border-t border-warmgray p-2"
      >
        <Input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder="Message the room…"
          maxLength={2000}
          className="h-10"
        />
        <Button
          type="submit"
          size="icon"
          className="size-10 shrink-0"
          disabled={sending || draft.trim() === ""}
          aria-label="Send"
        >
          <Send className="size-4" aria-hidden />
        </Button>
      </form>
    </div>
  );
}

function ChatRow({
  message,
  onReact,
}: {
  message: Message;
  onReact: (m: Message, emoji: string) => void;
}) {
  const [picking, setPicking] = useState(false);

  return (
    <div className="group flex flex-col">
      <div className="flex items-baseline gap-2">
        <span className="text-[12px] font-semibold text-text-primary">
          {message.profile.name || `@${message.profile.handle}`}
        </span>
        <span className="min-w-0 flex-1 text-[13px] break-words text-text-secondary">
          {message.deleted ? (
            <em className="opacity-60">message removed</em>
          ) : (
            message.body
          )}
        </span>
        <button
          type="button"
          aria-label="Add reaction"
          onClick={() => setPicking((v) => !v)}
          className="shrink-0 text-text-secondary opacity-0 group-hover:opacity-100"
        >
          <SmilePlus className="size-3.5" aria-hidden />
        </button>
      </div>

      {picking ? (
        <div className="mt-1 flex gap-1">
          {REACTIONS.map((e) => (
            <button
              key={e}
              type="button"
              onClick={() => {
                onReact(message, e);
                setPicking(false);
              }}
              className="rounded-full px-1 text-sm hover:scale-125"
            >
              {e}
            </button>
          ))}
        </div>
      ) : null}

      {message.reactions.length > 0 ? (
        <div className="mt-1 flex flex-wrap gap-1">
          {message.reactions.map((r) => (
            <button
              key={r.emoji}
              type="button"
              onClick={() => onReact(message, r.emoji)}
              className={`flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[11px] ${
                r.reacted_by_me
                  ? "border-teal bg-teal/12 text-teal-text"
                  : "border-warmgray text-text-secondary"
              }`}
            >
              {r.emoji} {r.count}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
