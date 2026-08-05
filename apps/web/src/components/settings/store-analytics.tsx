"use client";

import { useEffect, useState } from "react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  XAxis,
  YAxis,
} from "recharts";

import {
  ChartLegend,
  ChartTooltip,
  chartColor,
  sharedGrid,
  sharedXAxis,
  sharedYAxis,
} from "@/components/charts/chart-kit";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { StoreAnalytics as StoreAnalyticsData } from "@/lib/api/types";
import { formatPrice } from "@/lib/shop";

const RANGES = [7, 30, 90] as const;

/** Vendor store analytics (Shop P4): views, sales and conversion over a window. */
export function StoreAnalytics() {
  const [days, setDays] = useState<(typeof RANGES)[number]>(30);
  const [data, setData] = useState<StoreAnalyticsData | null>(null);

  useEffect(() => {
    let cancelled = false;
    // Keep the previous chart on screen while the new range loads (no flash).
    api
      .get<{ data: StoreAnalyticsData }>(`/api/v1/me/store/analytics?days=${days}`)
      .then((res) => {
        if (!cancelled) setData(res.data);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [days]);

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium text-text-primary">Analytics</h3>
        <div className="flex gap-1">
          {RANGES.map((r) => (
            <button
              key={r}
              type="button"
              onClick={() => setDays(r)}
              className={`rounded-full px-2.5 py-1 text-[12px] font-medium ${
                days === r
                  ? "bg-teal/15 text-teal-text"
                  : "text-text-secondary hover:bg-sage/10"
              }`}
            >
              {r}d
            </button>
          ))}
        </div>
      </div>

      {!data ? (
        <Skeleton className="h-56 w-full rounded-xl" />
      ) : (
        <>
          <div className="grid grid-cols-4 gap-2 rounded-lg border p-3">
            <Stat label="Views" value={data.totals.views.toLocaleString("en")} />
            <Stat label="Sales" value={data.totals.orders.toLocaleString("en")} />
            <Stat label="Conv." value={`${data.totals.conversion_pct}%`} />
            <Stat
              label="Earnings"
              value={formatPrice(data.totals.earnings_cents, data.totals.currency)}
            />
          </div>

          <div className="rounded-lg border p-3">
            <div className="h-52 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data.series} margin={{ left: 0, right: 8, top: 8 }}>
                  <defs>
                    <linearGradient id="storeViews" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor={chartColor(1)} stopOpacity={0.3} />
                      <stop offset="95%" stopColor={chartColor(1)} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="storeOrders" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor={chartColor(2)} stopOpacity={0.3} />
                      <stop offset="95%" stopColor={chartColor(2)} stopOpacity={0} />
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
                    fill="url(#storeViews)"
                    strokeWidth={2}
                  />
                  <Area
                    type="monotone"
                    dataKey="orders"
                    name="Sales"
                    stroke={chartColor(2)}
                    fill="url(#storeOrders)"
                    strokeWidth={2}
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
            <ChartLegend
              items={[
                { label: "Views", slot: 1 },
                { label: "Sales", slot: 2 },
              ]}
            />
          </div>

          {data.top_products.length > 0 ? (
            <div className="rounded-lg border p-3">
              <h4 className="mb-2 text-[12px] font-semibold text-text-primary">
                Top products by views
              </h4>
              <ul className="flex flex-col gap-1.5">
                {data.top_products.map((p) => (
                  <li
                    key={p.ulid}
                    className="flex items-center justify-between text-[13px]"
                  >
                    <span className="truncate text-text-primary">{p.title}</span>
                    <span className="shrink-0 tabular-nums text-text-secondary">
                      {p.views.toLocaleString("en")} views
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <span className="truncate text-sm font-semibold text-text-primary">
        {value}
      </span>
      <span className="text-[11px] text-text-secondary">{label}</span>
    </div>
  );
}
