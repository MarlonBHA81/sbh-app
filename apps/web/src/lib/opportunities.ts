import type { OpportunityType } from "@/lib/api/types";

/** Member-facing label + short blurb for each opportunity kind (V1 · GROW). */
export const OPPORTUNITY_TYPES: {
  key: OpportunityType;
  label: string;
}[] = [
  { key: "tender", label: "Tenders" },
  { key: "funding", label: "Funding" },
  { key: "grant", label: "Grants" },
  { key: "procurement", label: "Procurement" },
  { key: "programme", label: "Programmes" },
  { key: "competition", label: "Competitions" },
];

/** Singular label for a single opportunity's badge. */
const SINGULAR: Record<OpportunityType, string> = {
  tender: "Tender",
  funding: "Funding",
  grant: "Grant",
  procurement: "Procurement",
  programme: "Programme",
  competition: "Competition",
};

export function opportunityTypeLabel(type: OpportunityType): string {
  return SINGULAR[type] ?? type;
}

/**
 * Honest closing-date framing (no invented urgency): "Closes today",
 * "Closes tomorrow", "Closes in N days", a plain date further out, or
 * "No deadline". Returns null only when we truly can't tell.
 */
export function closesLabel(closesAt: string | null): string {
  if (!closesAt) return "No deadline";
  // Compare at day granularity in local time. Parsing the parts avoids
  // `new Date("YYYY-MM-DD")` being read as UTC midnight, and flooring both ends
  // to local midnight keeps the count honest: a date === today reads "Closes
  // today", not "tomorrow".
  const [y, m, d] = closesAt.split("-").map(Number);
  const end = new Date(y, m - 1, d);
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const days = Math.round((end.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
  if (days <= 0) return "Closes today";
  if (days === 1) return "Closes tomorrow";
  if (days <= 14) return `Closes in ${days} days`;
  return `Closes ${end.toLocaleDateString(undefined, { day: "numeric", month: "short" })}`;
}
