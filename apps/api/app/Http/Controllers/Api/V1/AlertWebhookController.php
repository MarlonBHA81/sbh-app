<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\FileIssueForAlert;
use App\Observability\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Inbound observability alert receiver. An error monitor (Sentry alert rule,
 * uptime check, …) POSTs a signed JSON payload here; a valid signature queues
 * a job that files a de-duplicated triage issue (see FileIssueForAlert).
 *
 * Runs unauthenticated (server-to-server) but is HMAC-signed: the shared secret
 * lives in config('observability.alert_webhook_secret') and the signature is
 * hash_hmac('sha256', rawBody, secret) — the same recipe DeliverWebhook uses
 * outbound. Fail-closed: a blank secret rejects every request.
 *
 * Accepts our `X-SBH-Signature` header or Sentry's `Sentry-Hook-Signature`.
 */
class AlertWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('observability.alert_webhook_secret', '');

        if (! $this->signatureIsValid($request, $secret)) {
            return response('', 401);
        }

        FileIssueForAlert::dispatch(Alert::fromRequest($request));

        return response('', 202);
    }

    private function signatureIsValid(Request $request, string $secret): bool
    {
        // Fail closed: without a configured secret nothing is trusted.
        if ($secret === '') {
            return false;
        }

        $provided = $request->header('X-SBH-Signature')
            ?? $request->header('Sentry-Hook-Signature');

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
