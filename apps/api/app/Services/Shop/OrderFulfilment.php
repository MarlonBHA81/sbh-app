<?php

namespace App\Services\Shop;

use App\Mail\NewSaleMail;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

/**
 * Post-payment fulfilment side effects, kept out of the webhook controller so
 * the same code path can be replayed by `shop:reconcile-orders` when the
 * original attempt failed (e.g. the queue was unavailable when the ITN landed).
 *
 * The orders.receipts_sent_at stamp is the recovery marker: it is only written
 * once the mailables have actually been handed off, so a paid order with a NULL
 * stamp is precisely "payment recorded, buyer never told".
 */
class OrderFulfilment
{
    /**
     * Queue the buyer's VAT receipt and the vendor's new-sale notice, then mark
     * the order as receipted. Idempotent: an order that already has a stamp is
     * skipped, so replaying this never double-sends.
     *
     * @return bool whether receipts were sent by this call
     */
    public function sendReceipts(Order $order): bool
    {
        if ($order->receipts_sent_at !== null) {
            return false;
        }

        $order->loadMissing('buyer.user', 'items', 'store.profile.user');

        $buyerEmail = $order->buyer?->user?->email;
        if ($buyerEmail !== null) {
            Mail::to($buyerEmail)->send(new OrderReceiptMail($order));
        }

        $vendorEmail = $order->store?->profile?->user?->email;
        if ($vendorEmail !== null && $vendorEmail !== $buyerEmail) {
            Mail::to($vendorEmail)->send(new NewSaleMail($order));
        }

        // Stamped last: if a mailable throws, the stamp stays NULL and the
        // reconciler will try again rather than the failure going unnoticed.
        $order->forceFill(['receipts_sent_at' => now()])->save();

        return true;
    }
}
