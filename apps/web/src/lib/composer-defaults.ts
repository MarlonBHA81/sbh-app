import type { PostType } from "@/lib/api/types";
import { isoToDatetimeLocal } from "@/lib/time";

/**
 * The user's most-used post type, to seed the composer's type picker
 * (UX pattern 1 — smart defaults).
 *
 * BACKEND-TODO: no endpoint exposes per-user post-type usage yet. When
 * `GET /api/v1/me/stats` (or similar) returns a `most_used_post_type`, resolve
 * it here. Returns null today so the composer falls back to "text" (SBH has no
 * "Discussion" type). Kept synchronous so it can seed a `useState` initializer.
 */
export function mostUsedPostType(): Exclude<PostType, "repost"> | null {
  return null;
}

/**
 * Smart-default helpers for the composer (UX pattern 1 — smart defaults).
 * These kill blank required fields by inferring the most likely value, so a
 * fresh event/job form opens pre-filled instead of empty.
 */

/**
 * The next Saturday at 10:00 in the viewer's local time, as a
 * `datetime-local` string. Always the *upcoming* Saturday — if today is
 * Saturday we jump to the following one, never a same-day default that may
 * already be in the past.
 */
export function nextSaturdayAt10Local(from: Date = new Date()): string {
  const d = new Date(from);
  const SATURDAY = 6;
  let delta = (SATURDAY - d.getDay() + 7) % 7;
  if (delta === 0) delta = 7;
  d.setDate(d.getDate() + delta);
  d.setHours(10, 0, 0, 0);
  return isoToDatetimeLocal(d.toISOString());
}

/**
 * Add `hours` to a `datetime-local` string, returning a `datetime-local`
 * string. Used to seed an event's end time one hour after its start.
 */
export function addHoursToLocal(local: string, hours: number): string {
  if (!local) return "";
  const d = new Date(local);
  d.setHours(d.getHours() + hours);
  return isoToDatetimeLocal(d.toISOString());
}
