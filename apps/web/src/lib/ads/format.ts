/**
 * Ad campaign bounds and formatting (Milestone 11). Bounds mirror the backend
 * config so the client can validate and preview before submitting.
 */

/** Campaign duration range, in days. */
export const DURATION_MIN_DAYS = 1;
export const DURATION_MAX_DAYS = 30;

const compactFormatter = new Intl.NumberFormat("en", { notation: "compact" });

/** Compact count for stat rows and reach previews, e.g. `12.3K`. */
export function formatCompact(n: number): string {
  return compactFormatter.format(n);
}
