import * as api from "@/lib/api/client";

export type ReportableType = "post" | "comment" | "profile";

export type ReportCategory =
  | "spam"
  | "harassment"
  | "hate_speech"
  | "violence"
  | "misinformation"
  | "nudity"
  | "scam"
  | "other";

export interface ReportCategoryOption {
  value: ReportCategory;
  label: string;
  description: string;
}

/** Human-facing labels + one-line descriptions for the report picker. */
export const REPORT_CATEGORIES: ReportCategoryOption[] = [
  { value: "spam", label: "Spam", description: "Misleading or repetitive content" },
  {
    value: "harassment",
    label: "Harassment",
    description: "Bullying or targeted abuse",
  },
  {
    value: "hate_speech",
    label: "Hate speech",
    description: "Attacks on people based on who they are",
  },
  {
    value: "violence",
    label: "Violence or threats",
    description: "Threats or graphic violence",
  },
  {
    value: "misinformation",
    label: "Misinformation",
    description: "False or misleading claims",
  },
  {
    value: "nudity",
    label: "Nudity or sexual content",
    description: "Explicit or adult content",
  },
  { value: "scam", label: "Scam or fraud", description: "Deceptive or fraudulent schemes" },
  {
    value: "other",
    label: "Something else",
    description: "It doesn't fit the categories above",
  },
];

export const REPORT_DETAILS_MAX = 1000;

export interface ReportResult {
  ulid: string;
  status: string;
  /** True when the backend matched an open report for this target (repeat report). */
  existing: boolean;
}

export function isExistingReport(report: ReportResult): boolean {
  return report.existing;
}

export async function submitReport(input: {
  reportable_type: ReportableType;
  reportable_ulid: string;
  category: ReportCategory;
  details?: string;
}): Promise<ReportResult> {
  return api.post<ReportResult>("/api/v1/reports", input);
}

function profilePath(handle: string, suffix: string): string {
  return `/api/v1/profiles/${encodeURIComponent(handle)}/${suffix}`;
}

export function blockProfile(handle: string): Promise<unknown> {
  return api.post(profilePath(handle, "block"));
}

export function unblockProfile(handle: string): Promise<unknown> {
  return api.del(profilePath(handle, "block"));
}

export function muteProfile(handle: string): Promise<unknown> {
  return api.post(profilePath(handle, "mute"));
}

export function unmuteProfile(handle: string): Promise<unknown> {
  return api.del(profilePath(handle, "mute"));
}
