import * as api from "@/lib/api/client";

export interface BugReportResult {
  ulid: string;
  status: string;
}

export const BUG_SUMMARY_MAX = 200;
export const BUG_DETAILS_MAX = 2000;

/**
 * Submit a bug report. The current URL and app version are captured
 * automatically so the reporter only has to describe what went wrong.
 */
export function submitBugReport(input: {
  summary: string;
  details?: string;
}): Promise<BugReportResult> {
  return api.post<BugReportResult>("/api/v1/bug-reports", {
    summary: input.summary,
    ...(input.details ? { details: input.details } : {}),
    url:
      typeof window !== "undefined"
        ? window.location.href.slice(0, 2000)
        : undefined,
    app_version: process.env.NEXT_PUBLIC_APP_VERSION ?? undefined,
  });
}
