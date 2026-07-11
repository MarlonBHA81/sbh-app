import type { Message } from "@/lib/api/types";

/**
 * A message in the thread. Optimistic sends carry a local `pending` status and
 * a `tempId`; on server ack the entry is replaced by the real message.
 */
export interface ChatMessage extends Message {
  pending?: "sending" | "failed";
  /** Stable local id for optimistic entries (equals `ulid` while pending). */
  tempId?: string;
}
