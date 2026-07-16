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
  const end = new Date(`${closesAt}T23:59:59`);
  const today = new Date();
  const days = Math.ceil(
    (end.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
  );
  if (days <= 0) return "Closes today";
  if (days === 1) return "Closes tomorrow";
  if (days <= 14) return `Closes in ${days} days`;
  return `Closes ${end.toLocaleDateString(undefined, { day: "numeric", month: "short" })}`;
}
