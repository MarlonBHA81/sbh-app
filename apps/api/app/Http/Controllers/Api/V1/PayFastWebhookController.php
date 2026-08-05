<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayUnavailable;
use App\Services\Shop\OrderFulfilment;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * PayFast ITN endpoint (Shop P2) — the authoritative payment confirmation. Runs
 * unauthenticated (server-to-server).
 *
 * Response contract, in order of precedence:
 *  - 503 when we could not REACH PayFast to verify (transport failure). PayFast
 *    redelivers, so a transient blip can't drop a real payment.
 *  - 200 in every other case, including an invalid/duplicate notification and a
 *    failure while sending receipts. A verified payment must never be re-driven
 *    by PayFast just because a downstream side effect failed — markPaid() would
 *    return false on the retry and the receipt would be lost permanently.
 *
 * Receipts that fail are left with orders.receipts_sent_at NULL and are picked
 * up by `shop:reconcile-orders`.
 */
class PayFastWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGateway $gateway,
        WebhookDispatcher $webhooks,
        OrderFulfilment $fulfilment,
    ): Response {
        try {
            $order = $gateway->handleWebhook($request);
        } catch (PaymentGatewayUnavailable $e) {
            // We do not know whether this notification was genuine. Ask PayFast
            // to send it again rather than silently discarding a real payment.
            report($e);

            return response('', 503);
        }

        // markPaid() is idempotent — a duplicate ITN returns false.
        if ($order !== null && $order->markPaid()) {
            $this->fulfil($order, $webhooks, $fulfilment);
        }

        return response('', 200);
    }

    /**
     * Post-payment side effects. Deliberately swallows failures: the payment is
     * already committed, and letting this bubble would turn a successful payment
     * into a PayFast retry that finds the order already paid and skips receipts
     * forever. Anything that fails here stays recoverable via receipts_sent_at.
     */
    private function fulfil(Order $order, WebhookDispatcher $webhooks, OrderFulfilment $fulfilment): void
    {
        $order->loadMissing('buyer.user', 'items', 'store.profile.user');

        try {
            $webhooks->contact(WebhookDispatcher::PURCHASE_COMPLETED, $order->buyer, [
                'order' => $order->ulid,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $fulfilment->sendReceipts($order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
