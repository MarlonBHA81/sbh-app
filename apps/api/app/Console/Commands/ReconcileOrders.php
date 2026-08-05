<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Shop\OrderFulfilment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Safety net for the two ways a PayFast payment can end up mishandled.
 *
 * 1. PAID BUT UN-RECEIPTED (auto-repairable). The ITN recorded the payment, then
 *    the receipt/webhook step failed. Replaying is safe because sendReceipts()
 *    is idempotent on receipts_sent_at.
 *
 * 2. STUCK PENDING (reported, not auto-repairable). An order that has sat
 *    pending well past a realistic checkout window either was abandoned by the
 *    buyer — harmless — or was paid while we were unable to verify the ITN.
 *    PayFast has no query API wired up here, so this command does NOT guess: it
 *    surfaces them so a human can check the PayFast dashboard. Reporting a real
 *    unknown beats silently pretending it's resolved.
 */
class ReconcileOrders extends Command
{
    protected $signature = 'shop:reconcile-orders
        {--pending-after=120 : Minutes after which a still-pending order is reported}
        {--dry-run : Report what would happen without sending anything}';

    protected $description = 'Replay missing order receipts and report orders stuck pending.';

    public function handle(OrderFulfilment $fulfilment): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $repaired = $this->replayMissingReceipts($fulfilment, $dryRun);
        $stuck = $this->reportStuckPending((int) $this->option('pending-after'));

        $this->newLine();
        $this->info("Receipts replayed: {$repaired}");
        $this->info("Orders stuck pending: {$stuck}");

        // Non-zero when something needs a human, so a cron wrapper can alert.
        return $stuck > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function replayMissingReceipts(OrderFulfilment $fulfilment, bool $dryRun): int
    {
        $repaired = 0;

        Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereNull('receipts_sent_at')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($fulfilment, $dryRun, &$repaired) {
                foreach ($orders as $order) {
                    $this->line("  paid order {$order->ulid} has no receipt".($dryRun ? ' (dry run)' : ''));

                    if ($dryRun) {
                        $repaired++;

                        continue;
                    }

                    try {
                        if ($fulfilment->sendReceipts($order)) {
                            $repaired++;
                        }
                    } catch (Throwable $e) {
                        // Leave the stamp NULL so the next run tries again.
                        report($e);
                        $this->warn("  failed to receipt {$order->ulid}: {$e->getMessage()}");
                    }
                }
            });

        return $repaired;
    }

    private function reportStuckPending(int $minutes): int
    {
        $cutoff = now()->subMinutes($minutes);

        $stuck = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get(['id', 'ulid', 'reference', 'total_cents', 'currency', 'created_at']);

        foreach ($stuck as $order) {
            $this->warn("  pending since {$order->created_at}: {$order->ulid} (ref {$order->reference})");
        }

        if ($stuck->isNotEmpty()) {
            // Surfaced to Sentry/log so it is visible without reading cron output.
            Log::warning('Orders stuck pending past the checkout window', [
                'count' => $stuck->count(),
                'older_than_minutes' => $minutes,
                'references' => $stuck->pluck('reference')->all(),
            ]);
        }

        return $stuck->count();
    }
}
