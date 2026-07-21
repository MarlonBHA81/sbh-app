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
            // A recording can land after the stream has ended, so it matches any
            // (even ended) session; a status change only applies to a live one.
            $recording = $event['recording_playback_url'] ?? null;

            $session = MasterclassLiveSession::query()
                ->where('provider_stream_id', $event['provider_stream_id'])
                ->when($recording === null, fn ($q) => $q->where('status', '!=', MasterclassLiveSession::STATUS_ENDED))
                ->latest('id')
                ->first();

            if ($session !== null) {
                $updates = [];

                if ($recording !== null) {
                    $updates['recording_playback_url'] = $recording;
                    $updates['recording_ready_at'] = now();
                }

                if (isset($event['status'])) {
                    $updates['status'] = $event['status'];

                    if ($event['status'] === MasterclassLiveSession::STATUS_ACTIVE && $session->started_at === null) {
                        $updates['started_at'] = now();
                    }
                }

                if ($updates !== []) {
                    $session->update($updates);
                }
            }
        }

        return response('', 200);
    }
}
