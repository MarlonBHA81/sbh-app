"use client";

import { Card } from "@/components/ui/card";
import { rand, type ProgrammeSummary } from "@/lib/esd";

/** The headline rollup tiles for a programme (suppliers, milestones, spend). */
export function SummaryCards({ summary }: { summary: ProgrammeSummary }) {
  const milestonePct =
    summary.milestones.total === 0
      ? 0
      : Math.round((summary.milestones.complete / summary.milestones.total) * 100);

  const tiles: { label: string; value: string; hint?: string }[] = [
    { label: "Suppliers", value: String(summary.suppliers), hint: `${summary.cohorts} cohort${summary.cohorts === 1 ? "" : "s"}` },
    {
      label: "Milestones",
      value: `${summary.milestones.complete}/${summary.milestones.total}`,
      hint: `${milestonePct}% complete`,
    },
    { label: "Disbursed", value: rand(summary.disbursed.actual_cents), hint: "paid to date" },
    { label: "Planned", value: rand(summary.disbursed.planned_cents), hint: "committed" },
  ];

  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
      {tiles.map((tile) => (
        <Card key={tile.label} className="flex flex-col gap-0.5 p-3">
          <span className="text-xs text-muted-foreground">{tile.label}</span>
          <span className="text-lg font-semibold tabular-nums">{tile.value}</span>
          {tile.hint ? (
            <span className="text-[11px] text-muted-foreground">{tile.hint}</span>
          ) : null}
        </Card>
      ))}
    </div>
  );
}
