import * as api from "@/lib/api/client";

/**
 * Typed client for the corporate self-serve ESD portal (see the API's
 * `corporate/*` routes). Every call rides the active-profile header the api
 * client already sets, so the server scopes results to the acting corporate.
 */

export type ProgrammeType = "supplier_development" | "enterprise_development";
export type ProgrammeStatus = "draft" | "active" | "closed";
export type EnrolmentStatus =
  | "invited"
  | "applied"
  | "accepted"
  | "active"
  | "completed"
  | "withdrawn"
  | "rejected";
export type MilestoneStatus = "pending" | "complete";
export type DisbursementKind = "grant" | "loan" | "in_kind";

export interface ProgrammeSummary {
  cohorts: number;
  suppliers: number;
  supplier_status: Record<string, number>;
  milestones: { total: number; complete: number };
  disbursed: { planned_cents: number; actual_cents: number };
}

export interface ProgrammeListItem {
  ulid: string;
  name: string;
  type: ProgrammeType;
  status: ProgrammeStatus;
  description: string | null;
  starts_at: string | null;
  ends_at: string | null;
  budget_cents: number | null;
  cohorts_count: number;
}

export interface CohortSummary {
  ulid: string;
  name: string;
  status: string;
  capacity: number | null;
  enrolments_count: number;
}

export interface ProgrammeDetail extends ProgrammeListItem {
  summary: ProgrammeSummary;
  cohorts: CohortSummary[];
}

export interface RosterEntry {
  ulid: string;
  status: EnrolmentStatus;
  supplier: { name: string | null; handle: string | null; is_verified: boolean };
  milestones_complete: number;
  milestones_total: number;
  planned_cents: number;
  actual_cents: number;
}

export interface CohortDetail {
  ulid: string;
  name: string;
  status: string;
  capacity: number | null;
  is_full: boolean;
  starts_at: string | null;
  ends_at: string | null;
  roster: RosterEntry[];
}

export interface ReportRow {
  cohort: string;
  supplier: string;
  handle: string;
  status: EnrolmentStatus;
  milestones_complete: number;
  milestones_total: number;
  planned_cents: number;
  actual_cents: number;
}

export interface ProgrammeReport {
  summary: ProgrammeSummary;
  suppliers: ReportRow[];
}

export interface Milestone {
  ulid: string;
  title: string;
  status: MilestoneStatus;
  due_at: string | null;
  completed_at: string | null;
  note: string | null;
}

export interface Disbursement {
  ulid: string;
  amount_cents: number;
  currency: string;
  kind: DisbursementKind;
  disbursed_at: string | null;
  is_paid: boolean;
  reference: string | null;
}

export type EnrolmentAction = "accept" | "activate" | "complete" | "reject";

export interface SupplierResult {
  ulid: string;
  name: string;
  handle: string;
}

const BASE = "/api/v1/corporate";

export function searchSuppliers(q: string) {
  const query = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : "";
  return api.get<{ data: SupplierResult[] }>(`${BASE}/suppliers${query}`);
}

export function listProgrammes() {
  return api.get<{ data: ProgrammeListItem[] }>(`${BASE}/programmes`);
}

export function createProgramme(body: {
  name: string;
  type: ProgrammeType;
  description?: string;
}) {
  return api.post<{ data: ProgrammeListItem }>(`${BASE}/programmes`, body);
}

export function getProgramme(ulid: string) {
  return api.get<{ data: ProgrammeDetail }>(`${BASE}/programmes/${ulid}`);
}

export function getProgrammeReport(ulid: string) {
  return api.get<{ data: ProgrammeReport }>(`${BASE}/programmes/${ulid}/report`);
}

export function createCohort(programmeUlid: string, body: { name: string; capacity?: number }) {
  return api.post<{ data: CohortSummary }>(`${BASE}/programmes/${programmeUlid}/cohorts`, body);
}

export function getCohort(ulid: string) {
  return api.get<{ data: CohortDetail }>(`${BASE}/cohorts/${ulid}`);
}

export function inviteSupplier(cohortUlid: string, supplierUlid: string) {
  return api.post<{ data: RosterEntry }>(`${BASE}/cohorts/${cohortUlid}/enrolments`, {
    supplier: supplierUlid,
  });
}

export function transitionEnrolment(enrolmentUlid: string, action: EnrolmentAction, note?: string) {
  return api.post<{ data: RosterEntry }>(`${BASE}/enrolments/${enrolmentUlid}/transition`, {
    action,
    note,
  });
}

export function addMilestone(enrolmentUlid: string, body: { title: string; due_at?: string }) {
  return api.post<{ data: Milestone }>(`${BASE}/enrolments/${enrolmentUlid}/milestones`, body);
}

export function updateMilestone(milestoneUlid: string, action: "complete" | "reopen") {
  return api.post<{ data: Milestone }>(`${BASE}/milestones/${milestoneUlid}/update`, { action });
}

export function addDisbursement(
  enrolmentUlid: string,
  body: { amount_cents: number; kind: DisbursementKind; reference?: string },
) {
  return api.post<{ data: Disbursement }>(`${BASE}/enrolments/${enrolmentUlid}/disbursements`, body);
}

export function markDisbursementPaid(disbursementUlid: string) {
  return api.post<{ data: Disbursement }>(`${BASE}/disbursements/${disbursementUlid}/paid`, {});
}

// --- formatting helpers -----------------------------------------------------

/** Format cents as a Rand amount, e.g. 250000 → "R2,500.00". */
export function rand(cents: number): string {
  return new Intl.NumberFormat("en-ZA", {
    style: "currency",
    currency: "ZAR",
  }).format(cents / 100);
}

export const PROGRAMME_TYPE_LABELS: Record<ProgrammeType, string> = {
  supplier_development: "Supplier development",
  enterprise_development: "Enterprise development",
};

export const STATUS_LABELS: Record<string, string> = {
  draft: "Draft",
  active: "Active",
  closed: "Closed",
  invited: "Invited",
  applied: "Applied",
  accepted: "Accepted",
  completed: "Completed",
  withdrawn: "Withdrawn",
  rejected: "Rejected",
};

/** Build a CSV string from the report rows (client-side download; no API needed). */
export function reportToCsv(rows: ReportRow[]): string {
  const header = [
    "Cohort",
    "Supplier",
    "Handle",
    "Status",
    "Milestones complete",
    "Milestones total",
    "Planned (ZAR)",
    "Disbursed (ZAR)",
  ];
  const escape = (value: string) => `"${value.replace(/"/g, '""')}"`;
  const lines = [header.map(escape).join(",")];
  for (const row of rows) {
    lines.push(
      [
        escape(row.cohort),
        escape(row.supplier),
        escape(row.handle),
        escape(row.status),
        String(row.milestones_complete),
        String(row.milestones_total),
        (row.planned_cents / 100).toFixed(2),
        (row.actual_cents / 100).toFixed(2),
      ].join(","),
    );
  }
  return lines.join("\n");
}
