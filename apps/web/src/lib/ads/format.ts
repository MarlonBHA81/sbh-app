/**
 * Ad campaign bounds and formatting (Milestone 11). Bounds mirror the backend
 * config so the client can validate and preview before submitting.
 */

/** Budget range in cents (ZAR): R50–R5000. */
export const BUDGET_MIN_CENTS = 5_000;
export const BUDGET_MAX_CENTS = 500_000;
/** Slider step: R50. */
export const BUDGET_STEP_CENTS = 5_000;

/** Campaign duration range, in days. */
export const DURATION_MIN_DAYS = 1;
export const DURATION_MAX_DAYS = 30;

/** Estimated cost per impression, in cents (reach ≈ budget_cents / 2). */
export const CENTS_PER_IMPRESSION = 2;

/** Estimated impressions a budget buys. */
export function estimatedReach(budgetCents: number): number {
  return Math.floor(budgetCents / CENTS_PER_IMPRESSION);
}

const randFormatter = new Intl.NumberFormat("en-ZA", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

/** Format integer cents as a whole-rand amount, e.g. `R1 500`. */
export function formatRand(cents: number): string {
  return `R${randFormatter.format(Math.round(cents / 100))}`;
}

const compactFormatter = new Intl.NumberFormat("en", { notation: "compact" });

/** Compact count for stat rows and reach previews, e.g. `12.3K`. */
export function formatCompact(n: number): string {
  return compactFormatter.format(n);
}
