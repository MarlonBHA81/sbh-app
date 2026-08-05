"use client";

import {
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
import type { CampaignSeriesPoint } from "@/lib/api/types";

const SERIES = [
  { key: "impressions", label: "Impressions", slot: 1 },
  { key: "clicks", label: "Post opens", slot: 2 },
  { key: "link_clicks", label: "Link clicks", slot: 3 },
] as const;

/** Daily impressions, post opens & link clicks for a campaign's detail view. */
export function CampaignChart({ data }: { data: CampaignSeriesPoint[] }) {
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
            {SERIES.map(({ key, label, slot }) => (
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
      <ChartLegend items={SERIES.map((s) => ({ ...s }))} />
    </div>
  );
}
