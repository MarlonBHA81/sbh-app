"use client";

import {
  Area,
  AreaChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  XAxis,
  YAxis,
} from "recharts";

import {
  chartColor,
  ChartLegend,
  ChartTooltip,
  sharedGrid,
  sharedXAxis,
  sharedYAxis,
} from "@/components/charts/chart-kit";
import type { AnalyticsSeriesPoint } from "@/lib/api/types";

/** Area chart of views over time (single series). */
export function ViewsAreaChart({ data }: { data: AnalyticsSeriesPoint[] }) {
  return (
    <div className="h-56 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          <defs>
            <linearGradient id="viewsFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={chartColor(1)} stopOpacity={0.35} />
              <stop offset="100%" stopColor={chartColor(1)} stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid {...sharedGrid()} />
          <XAxis dataKey="date" {...sharedXAxis()} />
          <YAxis {...sharedYAxis()} />
          <ChartTooltip />
          <Area
            type="monotone"
            dataKey="views"
            name="Views"
            stroke={chartColor(1)}
            strokeWidth={2}
            fill="url(#viewsFill)"
            activeDot={{ r: 4 }}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

const ENGAGEMENT_SERIES = [
  { key: "likes", label: "Likes", slot: 1 },
  { key: "comments", label: "Comments", slot: 2 },
  { key: "reposts", label: "Reposts", slot: 3 },
] as const;

/** Multi-line chart of likes / comments / reposts over time. */
export function EngagementChart({ data }: { data: AnalyticsSeriesPoint[] }) {
  return (
    <div className="flex flex-col gap-3">
      <div className="h-56 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart
            data={data}
            margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
          >
            <CartesianGrid {...sharedGrid()} />
            <XAxis dataKey="date" {...sharedXAxis()} />
            <YAxis {...sharedYAxis()} />
            <ChartTooltip />
            {ENGAGEMENT_SERIES.map(({ key, label, slot }) => (
              <Line
                key={key}
                type="monotone"
                dataKey={key}
                name={label}
                stroke={chartColor(slot)}
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 4 }}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
      <ChartLegend items={ENGAGEMENT_SERIES.map((s) => ({ ...s }))} />
    </div>
  );
}
