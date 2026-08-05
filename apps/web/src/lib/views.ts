import * as api from "@/lib/api/client";

/**
 * Feed view tracking (Milestone 11). Cards that stay ≥50% visible for 1s call
 * `markSeen`; ulids are batched and flushed to POST /posts/seen (≤20 per
 * request), debounced, plus a best-effort flush when the tab is hidden.
 *
 * Deliberately cheap: a tiny JSON body, session-level dedupe, and no work when
 * nothing is queued. Runs even in low-data mode (the payload is negligible).
 */

const FLUSH_MS = 4000;
const MAX_BATCH = 20;

/** Ulids already queued or sent this session — never tracked twice. */
const seen = new Set<string>();
/** Ulids waiting to be flushed. */
const queue = new Set<string>();

let timer: ReturnType<typeof setTimeout> | null = null;
let unloadBound = false;

function bindUnload(): void {
  if (unloadBound || typeof document === "undefined") return;
  unloadBound = true;
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") flushSeen();
  });
}

function scheduleFlush(): void {
  if (timer) return;
  timer = setTimeout(() => {
    timer = null;
    flushSeen();
  }, FLUSH_MS);
}

/** Flush up to one batch now; reschedules if more remain. */
export function flushSeen(): void {
  if (queue.size === 0) return;
  const batch = Array.from(queue).slice(0, MAX_BATCH);
  batch.forEach((ulid) => queue.delete(ulid));
  api.post("/api/v1/posts/seen", { ulids: batch }).catch(() => {
    // Best-effort: re-queue so a later flush retries (still session-deduped).
    batch.forEach((ulid) => queue.add(ulid));
  });
  if (queue.size > 0) scheduleFlush();
}

/** Record that a feed post became visible long enough to count as a view. */
export function markSeen(ulid: string): void {
  if (seen.has(ulid)) return;
  seen.add(ulid);
  queue.add(ulid);
  bindUnload();
  scheduleFlush();
}
