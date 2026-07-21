<?php

namespace App\Services\Streaming;

use App\Models\MasterclassLiveSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Mux (mux.com) live-streaming provider. Creates an RTMP live stream and exposes
 * its HLS playback URL. The ITN-equivalent here is the Mux webhook, which flips
 * the session status as the encoder connects/disconnects.
 */
class MuxStreamDriver implements StreamProvider
{
    /**
     * @param  array{token_id: ?string, token_secret: ?string, webhook_secret: ?string, api_url: string, ingest_url: string, playback_base: string, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return ! empty($this->config['token_id']) && ! empty($this->config['token_secret']);
    }

    public function createLiveStream(): array
    {
        $response = $this->client()
            ->post($this->config['api_url'].'/video/v1/live-streams', [
                'playback_policy' => ['public'],
                'latency_mode' => 'low',
                'reconnect_window' => 60,
                'new_asset_settings' => ['playback_policy' => ['public']],
            ])
            ->throw()
            ->json('data');

        $playbackId = $response['playback_ids'][0]['id'] ?? '';

        return [
            'provider' => 'mux',
            'provider_stream_id' => (string) ($response['id'] ?? ''),
            'stream_key' => (string) ($response['stream_key'] ?? ''),
            'ingest_url' => $this->config['ingest_url'],
            'playback_id' => $playbackId,
            'playback_url' => $playbackId === ''
                ? ''
                : $this->config['playback_base'].'/'.$playbackId.'.m3u8',
        ];
    }

    public function deleteLiveStream(string $providerStreamId): void
    {
        if ($providerStreamId === '') {
            return;
        }

        try {
            $this->client()->delete($this->config['api_url'].'/video/v1/live-streams/'.$providerStreamId);
        } catch (\Throwable) {
            // Best-effort teardown — a failed delete must not block ending a session.
        }
    }

    public function parseWebhook(Request $request): ?array
    {
        if (! $this->verifySignature($request)) {
            return null;
        }

        $type = $request->input('type');

        // A recording became available: keyed to the live stream via live_stream_id.
        if ($type === 'video.asset.ready' && $request->input('data.live_stream_id')) {
            $playbackId = $request->input('data.playback_ids.0.id');

            if (! is_string($playbackId) || $playbackId === '') {
                return null;
            }

            return [
                'provider_stream_id' => (string) $request->input('data.live_stream_id'),
                'recording_playback_url' => $this->config['playback_base'].'/'.$playbackId.'.m3u8',
            ];
        }

        $streamId = $request->input('data.id');

        if (! is_string($streamId) || $streamId === '') {
            return null;
        }

        $status = match ($type) {
            'video.live_stream.active' => MasterclassLiveSession::STATUS_ACTIVE,
            'video.live_stream.idle', 'video.live_stream.disconnected' => MasterclassLiveSession::STATUS_IDLE,
            default => null,
        };

        return $status === null ? null : ['provider_stream_id' => $streamId, 'status' => $status];
    }

    private function client()
    {
        return Http::withBasicAuth((string) $this->config['token_id'], (string) $this->config['token_secret'])
            ->timeout($this->config['timeout'] ?? 15)
            ->acceptJson();
    }

    /**
     * Verify the Mux-Signature header (t=timestamp,v1=hmac). Skipped when no
     * webhook secret is configured (e.g. local/dev).
     */
    private function verifySignature(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;
        if (empty($secret)) {
            return true;
        }

        $header = $request->header('Mux-Signature', '');
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$k] = $v;
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $expected = hash_hmac('sha256', $parts['t'].'.'.$request->getContent(), $secret);

        return hash_equals($expected, $parts['v1']);
    }
}
