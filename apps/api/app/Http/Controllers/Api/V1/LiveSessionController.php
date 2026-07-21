<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Masterclass;
use App\Models\MasterclassLiveSession;
use App\Models\Profile;
use App\Services\Streaming\StreamProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Live streaming for masterclass rooms (ask #4). Members (enrolled) watch the
 * HLS playback; the host (admin) creates the RTMP stream and gets the ingest
 * credentials. Payment-driver-style: the provider is swappable and the status
 * is confirmed by the provider webhook.
 */
class LiveSessionController extends Controller
{
    /** Current live session for the viewer (playback for members, ingest for hosts). */
    public function show(Request $request, Masterclass $masterclass, StreamProvider $provider): JsonResponse
    {
        abort_unless($masterclass->is_published, 404);

        $viewer = $this->activeProfile($request);
        $isAdmin = (bool) $request->user()?->is_admin;
        $enrolled = $masterclass->participants()->where('profile_id', $viewer->id)->exists();

        $session = $masterclass->currentLiveSession();

        return response()->json([
            'data' => [
                'enabled' => $provider->enabled(),
                'is_host' => $isAdmin,
                'can_watch' => $enrolled || $isAdmin,
                'session' => $session === null
                    ? null
                    : $this->serialize($session, canWatch: $enrolled || $isAdmin, isHost: $isAdmin),
            ],
        ]);
    }

    /** Create (or return the current) live stream — host only. */
    public function store(Request $request, Masterclass $masterclass, StreamProvider $provider): JsonResponse
    {
        abort_unless($provider->enabled(), 422, 'Live streaming is not configured yet.');

        $session = $masterclass->currentLiveSession();

        if ($session === null) {
            $creds = $provider->createLiveStream();

            $session = $masterclass->liveSessions()->create([
                'title' => $masterclass->title,
                'status' => MasterclassLiveSession::STATUS_IDLE,
                'provider' => $creds['provider'],
                'provider_stream_id' => $creds['provider_stream_id'],
                'stream_key' => $creds['stream_key'],
                'ingest_url' => $creds['ingest_url'],
                'playback_id' => $creds['playback_id'],
                'playback_url' => $creds['playback_url'],
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'data' => $this->serialize($session, canWatch: true, isHost: true),
        ], 201);
    }

    /** End the current live stream — host only. */
    public function end(Request $request, Masterclass $masterclass, StreamProvider $provider): Response
    {
        $session = $masterclass->currentLiveSession();

        if ($session !== null) {
            $provider->deleteLiveStream((string) $session->provider_stream_id);
            $session->update([
                'status' => MasterclassLiveSession::STATUS_ENDED,
                'ended_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MasterclassLiveSession $session, bool $canWatch, bool $isHost): array
    {
        $data = [
            'ulid' => $session->ulid,
            'status' => $session->status,
            'is_live' => $session->isActive(),
            'title' => $session->title,
            'started_at' => $session->started_at?->toIso8601String(),
            // Playback only for those allowed to watch (enrolled members / host).
            'playback_url' => $canWatch ? $session->playback_url : null,
        ];

        // RTMP ingest secrets are host-only.
        if ($isHost) {
            $data['ingest_url'] = $session->ingest_url;
            $data['stream_key'] = $session->stream_key;
        }

        return $data;
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
