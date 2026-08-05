import type {
  Conversation,
  ConversationParticipant,
  Message,
  ProfileLite,
} from "@/lib/api/types";

/** Fixed reaction palette (no dynamic emoji picker — keeps the bundle lean). */
export const REACTION_EMOJIS = ["👍", "❤️", "😂", "😮", "😢", "🔥"] as const;

/** The other participant in a DM, relative to the active profile. */
export function otherParticipant(
  conversation: Conversation,
  selfUlid: string | null,
): ConversationParticipant | null {
  const others = conversation.participants.filter(
    (p) => p.profile.ulid !== selfUlid,
  );
  return others[0] ?? conversation.participants[0] ?? null;
}

/** Display title for a conversation (group title, or the DM partner's name). */
export function conversationTitle(
  conversation: Conversation,
  selfUlid: string | null,
): string {
  if (conversation.kind === "group") {
    return conversation.title?.trim() || "Group";
  }
  return otherParticipant(conversation, selfUlid)?.profile.name ?? "Direct message";
}

/** Preview line for the conversation list (handles the deleted tombstone). */
export function lastMessagePreview(conversation: Conversation): {
  text: string;
  deleted: boolean;
} {
  const last = conversation.last_message;
  if (!last) return { text: "No messages yet", deleted: false };
  if (last.deleted) return { text: "Message deleted", deleted: true };
  return { text: last.preview, deleted: false };
}

/** Day bucket key (local) used to insert date separators in the thread. */
export function dayKey(iso: string): string {
  const d = new Date(iso);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

/** Human day label, e.g. "Today", "Yesterday", or "11 Jul 2026". */
export function dayLabel(iso: string): string {
  const d = new Date(iso);
  const now = new Date();
  const startOf = (x: Date) =>
    new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
  const diffDays = Math.round((startOf(now) - startOf(d)) / 86_400_000);
  if (diffDays === 0) return "Today";
  if (diffDays === 1) return "Yesterday";
  return new Intl.DateTimeFormat("en", {
    day: "numeric",
    month: "short",
    year: d.getFullYear() === now.getFullYear() ? undefined : "numeric",
  }).format(d);
}

/** Clock time for a message, e.g. "14:30". */
export function messageTime(iso: string): string {
  return new Intl.DateTimeFormat("en", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

/** True when two consecutive messages should visually group (same sender ≤5m). */
export function shouldGroup(prev: Message | null, current: Message): boolean {
  if (!prev) return false;
  if (prev.profile.ulid !== current.profile.ulid) return false;
  const gap =
    new Date(current.created_at).getTime() - new Date(prev.created_at).getTime();
  return gap >= 0 && gap < 5 * 60 * 1000;
}

// --- Realtime payload coercion -------------------------------------------
// Echo broadcasts are untyped; coerce defensively so a malformed frame can
// never crash the thread (realtime is enhancement-only).

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : null;
}

function toProfileLite(value: unknown): ProfileLite | null {
  const r = asRecord(value);
  if (!r || typeof r.ulid !== "string") return null;
  return {
    ulid: r.ulid,
    handle: typeof r.handle === "string" ? r.handle : "",
    name: typeof r.name === "string" ? r.name : "",
    avatar_url: typeof r.avatar_url === "string" ? r.avatar_url : null,
  };
}

/** Coerce a `.MessageSent` broadcast (or its `{message}` wrapper) to Message. */
export function toMessage(payload: unknown): Message | null {
  const root = asRecord(payload);
  if (!root) return null;
  const m = asRecord(root.message) ?? root;
  if (typeof m.ulid !== "string") return null;
  const profile = toProfileLite(m.profile);
  if (!profile) return null;

  const reactions = Array.isArray(m.reactions)
    ? m.reactions.flatMap((r) => {
        const rr = asRecord(r);
        return rr && typeof rr.emoji === "string"
          ? [
              {
                emoji: rr.emoji,
                count: typeof rr.count === "number" ? rr.count : 0,
                reacted_by_me: Boolean(rr.reacted_by_me),
              },
            ]
          : [];
      })
    : [];

  const media = Array.isArray(m.media) ? (m.media as Message["media"]) : [];

  let replyTo: Message["reply_to"] = null;
  const rt = asRecord(m.reply_to);
  if (rt && typeof rt.ulid === "string") {
    const sender = toProfileLite(rt.sender);
    replyTo = {
      ulid: rt.ulid,
      body: typeof rt.body === "string" ? rt.body : null,
      sender: sender ?? { ulid: "", handle: "", name: "", avatar_url: null },
    };
  }

  return {
    ulid: m.ulid,
    body: typeof m.body === "string" ? m.body : null,
    hidden: Boolean(m.hidden),
    deleted: Boolean(m.deleted),
    reply_to: replyTo,
    reactions,
    media,
    profile,
    created_at:
      typeof m.created_at === "string" ? m.created_at : new Date().toISOString(),
  };
}
