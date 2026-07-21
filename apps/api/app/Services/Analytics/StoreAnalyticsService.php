<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates a store's view + sales metrics into zero-filled per-day series and
 * totals for the vendor analytics dashboard (Shop P4). Views come from
 * store_stats_daily / product_stats_daily; sales come from paid orders.
 */
class StoreAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function forStore(Store $store, int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $from = now()->utc()->startOfDay()->subDays($days - 1);
        $dates = $this->dateRange($from, $days);

        $viewsByDate = DB::table('store_stats_daily')
            ->where('store_id', $store->id)
            ->where('date', '>=', $from->toDateString())
            ->pluck('views', 'date');

        $salesByDate = Order::query()
            ->where('store_id', $store->id)
            ->where('status', Order::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as orders, SUM(total_cents) as gross, SUM(vendor_amount_cents) as earnings')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $series = [];
        $totalViews = 0;
        $totalOrders = 0;
        $totalGross = 0;
        $totalEarnings = 0;

        foreach ($dates as $date) {
            $views = (int) ($viewsByDate[$date] ?? 0);
            $row = $salesByDate[$date] ?? null;
            $orders = (int) ($row->orders ?? 0);
            $gross = (int) ($row->gross ?? 0);
            $earnings = (int) ($row->earnings ?? 0);

            $series[] = [
                'date' => $date,
                'views' => $views,
                'orders' => $orders,
                'gross_cents' => $gross,
                'earnings_cents' => $earnings,
            ];

            $totalViews += $views;
            $totalOrders += $orders;
            $totalGross += $gross;
            $totalEarnings += $earnings;
        }

        return [
            'days' => $days,
            'series' => $series,
            'totals' => [
                'views' => $totalViews,
                'orders' => $totalOrders,
                'gross_cents' => $totalGross,
                'earnings_cents' => $totalEarnings,
                'conversion_pct' => $totalViews > 0
                    ? round($totalOrders / $totalViews * 100, 1)
                    : 0.0,
                'currency' => 'ZAR',
            ],
            'top_products' => $this->topProducts($store, $from),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(Store $store, Carbon $from): array
    {
        return DB::table('product_stats_daily as psd')
            ->join('products as p', 'p.id', '=', 'psd.product_id')
            ->where('p.store_id', $store->id)
            ->where('psd.date', '>=', $from->toDateString())
            ->groupBy('p.id', 'p.ulid', 'p.title')
            ->orderByDesc(DB::raw('SUM(psd.views)'))
            ->limit(5)
            ->get(['p.ulid', 'p.title', DB::raw('SUM(psd.views) as views')])
            ->map(fn ($r) => [
                'ulid' => $r->ulid,
                'title' => $r->title,
                'views' => (int) $r->views,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function dateRange(Carbon $from, int $days): array
    {
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $from->copy()->addDays($i)->toDateString();
        }

        return $dates;
    }
}
