<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\NewSaleMail;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * PayFast ITN endpoint (Shop P2) — the authoritative payment confirmation. Runs
 * unauthenticated (server-to-server). Always returns 200 so PayFast stops
 * retrying; the order is only marked paid when the notification verifies.
 */
class PayFastWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, WebhookDispatcher $webhooks): Response
    {
        $order = $gateway->handleWebhook($request);

        // markPaid() is idempotent — a duplicate ITN returns false.
        if ($order !== null && $order->markPaid()) {
            $order->loadMissing('buyer.user', 'items', 'store.profile.user');

            $webhooks->contact(WebhookDispatcher::PURCHASE_COMPLETED, $order->buyer, [
                'order' => $order->ulid,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
            ]);

            $this->sendReceipts($order);
        }

        return response('', 200);
    }

    /** Queue the buyer's VAT receipt and the vendor's new-sale notice. */
    private function sendReceipts(Order $order): void
    {
        $buyerEmail = $order->buyer?->user?->email;
        if ($buyerEmail !== null) {
            Mail::to($buyerEmail)->send(new OrderReceiptMail($order));
        }

        $vendorEmail = $order->store?->profile?->user?->email;
        if ($vendorEmail !== null && $vendorEmail !== $buyerEmail) {
            Mail::to($vendorEmail)->send(new NewSaleMail($order));
        }
    }
}
