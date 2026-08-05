"use client";

import type { ComponentProps } from "react";
import { CartesianGrid, Tooltip, XAxis, YAxis } from "recharts";

/**
 * Shared theming for recharts across Insights and the Ad Center. Colors come
 * from the design-system `--chart-1..5` CSS variables (which redefine
 * themselves in dark mode), and all axis/grid ink uses theme tokens so charts
 * read correctly in both themes with no hardcoded hex.
 */

/** Series color for the given 1-based slot, e.g. `chartColor(2)`. */
export function chartColor(slot: number): string {
  return `var(--chart-${slot})`;
}

/** Short day label for a `YYYY-MM-DD` date, e.g. `7 Jul`. */
export function formatDayTick(date: string): string {
  const parsed = new Date(`${date}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return date;
  return parsed.toLocaleDateString("en-ZA", {
    day: "numeric",
    month: "short",
  });
}

const compact = new Intl.NumberFormat("en", { notation: "compact" });

export function sharedGrid(): ComponentProps<typeof CartesianGrid> {
  return {
    strokeDasharray: "3 3",
    stroke: "var(--border)",
    vertical: false,
  };
}

export function sharedXAxis(): ComponentProps<typeof XAxis> {
  return {
    tickLine: false,
    axisLine: false,
    tickMargin: 8,
    minTickGap: 24,
    tickFormatter: formatDayTick,
    tick: { fill: "var(--muted-foreground)", fontSize: 12 },
  };
}

export function sharedYAxis(): ComponentProps<typeof YAxis> {
  return {
    tickLine: false,
    axisLine: false,
    width: 36,
    tickFormatter: (value: number) => compact.format(value),
    tick: { fill: "var(--muted-foreground)", fontSize: 12 },
    allowDecimals: false,
  };
}

interface TooltipPayloadItem {
  name?: string;
  value?: number | string;
  color?: string;
  dataKey?: string | number;
}

/** Theme-aware tooltip; label is the (formatted) date, rows are the series. */
function ChartTooltipContent({
  active,
  payload,
  label,
}: {
  active?: boolean;
  payload?: TooltipPayloadItem[];
  label?: string;
}) {
  if (!active || !payload || payload.length === 0) return null;
  return (
    <div className="rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
      <p className="mb-1 font-medium">
        {typeof label === "string" ? formatDayTick(label) : label}
      </p>
      <ul className="flex flex-col gap-1">
        {payload.map((item, index) => (
          <li
            key={item.dataKey ?? index}
            className="flex items-center gap-2 tabular-nums"
          >
            <span
              aria-hidden
              className="size-2 rounded-full"
              style={{ backgroundColor: item.color }}
            />
            <span className="text-muted-foreground capitalize">
              {item.name}
            </span>
            <span className="ml-auto font-medium">
              {typeof item.value === "number"
                ? item.value.toLocaleString("en")
                : item.value}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function ChartTooltip() {
  return (
    <Tooltip
      cursor={{ stroke: "var(--border)" }}
      content={<ChartTooltipContent />}
    />
  );
}

/** A colored-dot legend; labels stay in muted ink, dots carry identity. */
export function ChartLegend({
  items,
}: {
  items: { label: string; slot: number }[];
}) {
  return (
    <ul className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
      {items.map(({ label, slot }) => (
        <li key={label} className="flex items-center gap-1.5">
          <span
            aria-hidden
            className="size-2.5 rounded-full"
            style={{ backgroundColor: chartColor(slot) }}
          />
          {label}
        </li>
      ))}
    </ul>
  );
}
