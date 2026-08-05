<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery marker for post-payment receipts.
 *
 * The ITN webhook records the payment first and sends receipts afterwards. If
 * that second step fails (queue unavailable, SMTP down) the payment is still
 * correct but the buyer was never told — and PayFast will not redeliver, since
 * the notification was accepted. A NULL stamp on a paid order is exactly that
 * state, and `shop:reconcile-orders` replays it.
 *
 * Deliberately no ->after(): a plain nullable column with no positional clause
 * is an INSTANT add in MySQL 8, so this does not rebuild the orders table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('receipts_sent_at')->nullable();
            $table->index(['status', 'receipts_sent_at']);
        });

        // Existing paid orders predate this column. Treat them as receipted so
        // the reconciler doesn't email every historical customer on first run.
        DB::table('orders')
            ->where('status', 'paid')
            ->update(['receipts_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'receipts_sent_at']);
            $table->dropColumn('receipts_sent_at');
        });
    }
};
