"use client";

import { BadgeCheck, Plus, Search, Users } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import {
  addDisbursement,
  addMilestone,
  getCohort,
  inviteSupplier,
  rand,
  searchSuppliers,
  STATUS_LABELS,
  transitionEnrolment,
  type CohortDetail,
  type DisbursementKind,
  type EnrolmentAction,
  type RosterEntry,
  type SupplierResult,
} from "@/lib/esd";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  active: "default",
  accepted: "default",
  completed: "secondary",
  invited: "outline",
  applied: "outline",
  rejected: "destructive",
  withdrawn: "destructive",
};

/** Which corporate-side transitions are offered for each status. */
const ACTIONS: Record<string, EnrolmentAction[]> = {
  invited: ["accept", "reject"],
  applied: ["accept", "reject"],
  accepted: ["activate"],
  active: ["complete"],
};

const ACTION_LABELS: Record<EnrolmentAction, string> = {
  accept: "Accept",
  activate: "Activate",
  complete: "Complete",
  reject: "Reject",
};

export function CohortDetailView({ ulid }: { ulid: string }) {
  const [cohort, setCohort] = useState<CohortDetail | null>(null);
  const [missing, setMissing] = useState(false);

  const reload = useCallback(() => {
    getCohort(ulid)
      .then((res) => setCohort(res.data))
      .catch(() => setMissing(true));
  }, [ulid]);

  useEffect(() => {
    let active = true;
    getCohort(ulid)
      .then((res) => active && setCohort(res.data))
      .catch(() => active && setMissing(true));
    return () => {
      active = false;
    };
  }, [ulid]);

  if (missing) {
    return (
      <EmptyState
        icon={Users}
        title="Cohort not found"
        description="It may belong to another sponsor, or no longer exist."
      />
    );
  }

  if (!cohort) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <h1 className="text-xl font-semibold tracking-tight">{cohort.name}</h1>
          <Badge variant="outline">
            {cohort.roster.length}
            {cohort.capacity ? ` / ${cohort.capacity}` : ""} suppliers
          </Badge>
        </div>
        <InviteDialog cohortUlid={cohort.ulid} disabled={cohort.is_full} onDone={reload} />
      </div>

      {cohort.roster.length === 0 ? (
        <EmptyState
          icon={Users}
          title="No suppliers yet"
          description="Invite a verified business to enrol it in this cohort."
        />
      ) : (
        <ul className="flex flex-col gap-2">
          {cohort.roster.map((entry) => (
            <RosterCard key={entry.ulid} entry={entry} onChanged={reload} />
          ))}
        </ul>
      )}
    </div>
  );
}

function RosterCard({ entry, onChanged }: { entry: RosterEntry; onChanged: () => void }) {
  const [busy, setBusy] = useState(false);

  async function transition(action: EnrolmentAction) {
    setBusy(true);
    try {
      await transitionEnrolment(entry.ulid, action);
      toast.success(`Supplier ${action}ed`);
      onChanged();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not update");
    } finally {
      setBusy(false);
    }
  }

  return (
    <li>
      <Card className="flex flex-col gap-3 p-4">
        <div className="flex items-start justify-between gap-2">
          <div className="flex flex-col gap-0.5">
            <span className="flex items-center gap-1.5 font-medium">
              {entry.supplier.name ?? "Supplier"}
              {entry.supplier.is_verified ? (
                <BadgeCheck className="size-4 text-primary" aria-label="Verified" />
              ) : null}
            </span>
            {entry.supplier.handle ? (
              <span className="text-xs text-muted-foreground">@{entry.supplier.handle}</span>
            ) : null}
          </div>
          <Badge variant={STATUS_VARIANT[entry.status] ?? "outline"}>
            {STATUS_LABELS[entry.status] ?? entry.status}
          </Badge>
        </div>

        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span>
            Milestones {entry.milestones_complete}/{entry.milestones_total}
          </span>
          <span>Planned {rand(entry.planned_cents)}</span>
          <span>Disbursed {rand(entry.actual_cents)}</span>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {(ACTIONS[entry.status] ?? []).map((action) => (
            <Button
              key={action}
              size="sm"
              variant={action === "reject" ? "outline" : "default"}
              disabled={busy}
              onClick={() => transition(action)}
            >
              {ACTION_LABELS[action]}
            </Button>
          ))}
          <TrackDialog enrolmentUlid={entry.ulid} onDone={onChanged} />
        </div>
      </Card>
    </li>
  );
}

function InviteDialog({
  cohortUlid,
  disabled,
  onDone,
}: {
  cohortUlid: string;
  disabled: boolean;
  onDone: () => void;
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<SupplierResult[] | null>(null);
  const [invitingUlid, setInvitingUlid] = useState<string | null>(null);

  // Debounced search over verified suppliers whenever the dialog is open.
  useEffect(() => {
    if (!open) return;
    let active = true;
    const handle = setTimeout(() => {
      if (active) setResults(null);
      searchSuppliers(query)
        .then((res) => active && setResults(res.data))
        .catch(() => active && setResults([]));
    }, 250);
    return () => {
      active = false;
      clearTimeout(handle);
    };
  }, [query, open]);

  async function invite(supplier: SupplierResult) {
    setInvitingUlid(supplier.ulid);
    try {
      await inviteSupplier(cohortUlid, supplier.ulid);
      toast.success(`${supplier.name} invited`);
      setOpen(false);
      setQuery("");
      onDone();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not invite supplier");
    } finally {
      setInvitingUlid(null);
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" disabled={disabled}>
          <Plus className="size-4" aria-hidden />
          Invite supplier
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Invite a verified supplier</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-search" className="sr-only">
            Search suppliers
          </Label>
          <div className="relative">
            <Search
              className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground"
              aria-hidden
            />
            <Input
              id="supplier-search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search verified suppliers by name or handle"
              className="pl-8"
              autoComplete="off"
            />
          </div>
          <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
            {results === null ? (
              <>
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-12 w-full" />
              </>
            ) : results.length === 0 ? (
              <li className="px-1 py-6 text-center text-sm text-muted-foreground">
                No verified suppliers found.
              </li>
            ) : (
              results.map((supplier) => (
                <li key={supplier.ulid}>
                  <button
                    type="button"
                    disabled={invitingUlid !== null}
                    onClick={() => invite(supplier)}
                    className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left transition-colors hover:bg-accent/60 disabled:opacity-60"
                  >
                    <span className="flex flex-col">
                      <span className="flex items-center gap-1.5 text-sm font-medium">
                        {supplier.name}
                        <BadgeCheck className="size-3.5 text-primary" aria-label="Verified" />
                      </span>
                      <span className="text-xs text-muted-foreground">@{supplier.handle}</span>
                    </span>
                    <span className="text-xs text-primary">
                      {invitingUlid === supplier.ulid ? "Inviting…" : "Invite"}
                    </span>
                  </button>
                </li>
              ))
            )}
          </ul>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function TrackDialog({ enrolmentUlid, onDone }: { enrolmentUlid: string; onDone: () => void }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [amount, setAmount] = useState("");
  const [kind, setKind] = useState<DisbursementKind>("grant");
  const [busy, setBusy] = useState(false);

  async function saveMilestone() {
    if (!title.trim()) return;
    setBusy(true);
    try {
      await addMilestone(enrolmentUlid, { title: title.trim() });
      toast.success("Milestone added");
      setTitle("");
      onDone();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not add milestone");
    } finally {
      setBusy(false);
    }
  }

  async function saveDisbursement() {
    const rands = Number(amount);
    if (!Number.isFinite(rands) || rands <= 0) return;
    setBusy(true);
    try {
      await addDisbursement(enrolmentUlid, { amount_cents: Math.round(rands * 100), kind });
      toast.success("Disbursement recorded");
      setAmount("");
      onDone();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not record disbursement");
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          Track
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Track development</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="milestone-title">Add a milestone</Label>
            <div className="flex gap-2">
              <Input
                id="milestone-title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Tax clearance obtained"
                maxLength={200}
              />
              <Button onClick={saveMilestone} disabled={busy || !title.trim()}>
                Add
              </Button>
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="disbursement-amount">Record a disbursement (ZAR)</Label>
            <div className="flex gap-2">
              <Input
                id="disbursement-amount"
                type="number"
                min={0}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="5000"
              />
              <Select value={kind} onValueChange={(v) => setKind(v as DisbursementKind)}>
                <SelectTrigger className="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="grant">Grant</SelectItem>
                  <SelectItem value="loan">Loan</SelectItem>
                  <SelectItem value="in_kind">In-kind</SelectItem>
                </SelectContent>
              </Select>
              <Button onClick={saveDisbursement} disabled={busy || !amount}>
                Add
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
