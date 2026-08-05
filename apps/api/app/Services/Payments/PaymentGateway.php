<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * A payment provider (Shop P2). Checkout builds a provider redirect; the
 * webhook (ITN) is the authoritative confirmation that an order was paid.
 */
interface PaymentGateway
{
    /** Whether a real, configured provider is active. */
    public function enabled(): bool;

    /**
     * Build the redirect to the provider's hosted payment page.
     *
     * @return array{process_url: string, fields: array<string, string>}
     */
    public function checkout(Order $order): array;

    /**
     * Verify a provider webhook (ITN). Returns the Order it confirms as paid, or
     * null when the notification is invalid / not a completed payment.
     */
    public function handleWebhook(Request $request): ?Order;
}
