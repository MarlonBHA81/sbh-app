<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Payments disabled (Shop P2). Checkout is refused upstream via enabled(); the
 * webhook never confirms anything.
 */
class NullPaymentDriver implements PaymentGateway
{
    public function enabled(): bool
    {
        return false;
    }

    public function checkout(Order $order): array
    {
        return ['process_url' => config('payments.frontend_url').'/shop', 'fields' => []];
    }

    public function handleWebhook(Request $request): ?Order
    {
        return null;
    }
}
