<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MasterclassLiveSession;
use App\Services\Streaming\StreamProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Streaming provider webhook (ask #4) — the authoritative live-status signal.
 * Runs unauthenticated (server-to-server); always returns 200 so the provider
 * stops retrying. Flips the session status as the encoder connects/disconnects.
 */
class StreamingWebhookController extends Controller
{
    public function __invoke(Request $request, StreamProvider $provider): Response
    {
        $event = $provider->parseWebhook($request);

        if ($event !== null) {
            $session = MasterclassLiveSession::query()
                ->where('provider_stream_id', $event['provider_stream_id'])
                ->where('status', '!=', MasterclassLiveSession::STATUS_ENDED)
                ->latest('id')
                ->first();

            if ($session !== null) {
                $updates = ['status' => $event['status']];

                if ($event['status'] === MasterclassLiveSession::STATUS_ACTIVE && $session->started_at === null) {
                    $updates['started_at'] = now();
                }

                $session->update($updates);
            }
        }

        return response('', 200);
    }
}
