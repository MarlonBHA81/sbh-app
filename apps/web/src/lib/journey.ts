/**
 * Business Journey stages (V1). Captured at signup and editable in settings;
 * the app personalises Home, opportunities and learning from it. Keys mirror
 * the backend `Profile::JOURNEY_STAGES`; labels are the member-facing wording.
 */
export const JOURNEY_STAGES = [
  { key: "starting", label: "Starting a business" },
  { key: "finding_customers", label: "Finding customers" },
  { key: "growing_sales", label: "Growing sales" },
  { key: "hiring", label: "Hiring staff" },
  { key: "expanding", label: "Expanding nationally" },
  { key: "exporting", label: "Exporting" },
] as const;

export type JourneyStage = (typeof JOURNEY_STAGES)[number]["key"];

export function journeyLabel(key: string | null | undefined): string | null {
  return JOURNEY_STAGES.find((s) => s.key === key)?.label ?? null;
}
