<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGateway;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            $order->loadMissing('buyer');

            $webhooks->contact(WebhookDispatcher::PURCHASE_COMPLETED, $order->buyer, [
                'order' => $order->ulid,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
            ]);
        }

        return response('', 200);
    }
}
