"use client";

import { BarChart3, Layers, Plus } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { SummaryCards } from "@/components/corporate/summary-cards";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import {
  createCohort,
  getProgramme,
  PROGRAMME_TYPE_LABELS,
  STATUS_LABELS,
  type CohortSummary,
  type ProgrammeDetail,
} from "@/lib/esd";

export function ProgrammeDetailView({ ulid }: { ulid: string }) {
  const [programme, setProgramme] = useState<ProgrammeDetail | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    let active = true;
    getProgramme(ulid)
      .then((res) => active && setProgramme(res.data))
      .catch(() => active && setMissing(true));
    return () => {
      active = false;
    };
  }, [ulid]);

  function onCohortCreated(cohort: CohortSummary) {
    setProgramme((prev) =>
      prev ? { ...prev, cohorts: [...prev.cohorts, cohort] } : prev,
    );
  }

  if (missing) {
    return (
      <EmptyState
        icon={Layers}
        title="Programme not found"
        description="It may belong to another sponsor, or no longer exist."
      />
    );
  }

  if (!programme) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-20 w-full" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <h1 className="text-xl font-semibold tracking-tight">{programme.name}</h1>
            <Badge variant="outline">
              {STATUS_LABELS[programme.status] ?? programme.status}
            </Badge>
          </div>
          <span className="text-xs text-muted-foreground">
            {PROGRAMME_TYPE_LABELS[programme.type]}
          </span>
        </div>
        <Button asChild variant="outline" size="sm">
          <Link href={`/corporate/programmes/${programme.ulid}/report`}>
            <BarChart3 className="size-4" aria-hidden />
            Report
          </Link>
        </Button>
      </div>

      <SummaryCards summary={programme.summary} />

      <div className="flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold">Cohorts</h2>
        <CreateCohortDialog programmeUlid={programme.ulid} onCreated={onCohortCreated} />
      </div>

      {programme.cohorts.length === 0 ? (
        <EmptyState
          icon={Layers}
          title="No cohorts yet"
          description="Add an intake to start enrolling suppliers."
        />
      ) : (
        <ul className="flex flex-col gap-2">
          {programme.cohorts.map((cohort) => (
            <li key={cohort.ulid}>
              <Link href={`/corporate/cohorts/${cohort.ulid}`}>
                <Card className="flex items-center justify-between gap-3 p-4 transition-colors hover:bg-accent/50">
                  <span className="font-medium">{cohort.name}</span>
                  <span className="text-xs text-muted-foreground">
                    {cohort.enrolments_count} supplier
                    {cohort.enrolments_count === 1 ? "" : "s"}
                    {cohort.capacity ? ` / ${cohort.capacity}` : ""}
                  </span>
                </Card>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function CreateCohortDialog({
  programmeUlid,
  onCreated,
}: {
  programmeUlid: string;
  onCreated: (cohort: CohortSummary) => void;
}) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);

  async function submit() {
    if (!name.trim()) return;
    setSaving(true);
    try {
      const res = await createCohort(programmeUlid, { name: name.trim() });
      onCreated(res.data);
      toast.success("Cohort added");
      setName("");
      setOpen(false);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not add cohort");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <Plus className="size-4" aria-hidden />
          Add cohort
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New cohort</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="cohort-name">Name</Label>
          <Input
            id="cohort-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Intake 1"
            maxLength={160}
          />
        </div>
        <DialogFooter>
          <Button onClick={submit} disabled={saving || !name.trim()}>
            Add
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
