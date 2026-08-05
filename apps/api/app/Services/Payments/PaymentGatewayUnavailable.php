<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * The gateway could not be reached to verify a notification.
 *
 * This is deliberately distinct from "the notification is invalid". An invalid
 * notification is a definitive answer — we can safely 200 it so the gateway
 * stops retrying. A transport failure is NOT an answer: we do not know whether
 * the payment was genuine, so the webhook must respond 5xx and let the gateway
 * redeliver. Collapsing the two is how a confirmed payment gets silently lost.
 */
class PaymentGatewayUnavailable extends RuntimeException {}
