"use client";

import { BarChart3, Download } from "lucide-react";
import { useEffect, useState } from "react";

import { SummaryCards } from "@/components/corporate/summary-cards";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  getProgrammeReport,
  rand,
  reportToCsv,
  STATUS_LABELS,
  type ProgrammeReport,
} from "@/lib/esd";

export function ReportView({ ulid }: { ulid: string }) {
  const [report, setReport] = useState<ProgrammeReport | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    let active = true;
    getProgrammeReport(ulid)
      .then((res) => active && setReport(res.data))
      .catch(() => active && setMissing(true));
    return () => {
      active = false;
    };
  }, [ulid]);

  function download() {
    if (!report) return;
    const csv = reportToCsv(report.suppliers);
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "programme-report.csv";
    link.click();
    URL.revokeObjectURL(url);
  }

  if (missing) {
    return (
      <EmptyState
        icon={BarChart3}
        title="Report unavailable"
        description="This programme may belong to another sponsor, or no longer exist."
      />
    );
  }

  if (!report) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-20 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <h1 className="text-xl font-semibold tracking-tight">Programme report</h1>
        <Button
          size="sm"
          variant="outline"
          onClick={download}
          disabled={report.suppliers.length === 0}
        >
          <Download className="size-4" aria-hidden />
          CSV
        </Button>
      </div>

      <SummaryCards summary={report.summary} />

      {report.suppliers.length === 0 ? (
        <EmptyState
          icon={BarChart3}
          title="No suppliers yet"
          description="Enrol suppliers to populate the report."
        />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Supplier</TableHead>
                <TableHead>Cohort</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Milestones</TableHead>
                <TableHead className="text-right">Planned</TableHead>
                <TableHead className="text-right">Disbursed</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {report.suppliers.map((row, i) => (
                <TableRow key={`${row.handle}-${i}`}>
                  <TableCell className="font-medium">{row.supplier}</TableCell>
                  <TableCell className="text-muted-foreground">{row.cohort}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{STATUS_LABELS[row.status] ?? row.status}</Badge>
                  </TableCell>
                  <TableCell className="text-right tabular-nums">
                    {row.milestones_complete}/{row.milestones_total}
                  </TableCell>
                  <TableCell className="text-right tabular-nums">{rand(row.planned_cents)}</TableCell>
                  <TableCell className="text-right tabular-nums">{rand(row.actual_cents)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
