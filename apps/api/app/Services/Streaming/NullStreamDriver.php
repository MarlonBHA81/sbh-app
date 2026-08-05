<?php

namespace App\Services\Streaming;

use Illuminate\Http\Request;

/** No-op streaming provider — live streaming is disabled. */
class NullStreamDriver implements StreamProvider
{
    public function enabled(): bool
    {
        return false;
    }

    public function createLiveStream(): array
    {
        return [
            'provider' => 'null',
            'provider_stream_id' => '',
            'stream_key' => '',
            'ingest_url' => '',
            'playback_id' => '',
            'playback_url' => '',
        ];
    }

    public function deleteLiveStream(string $providerStreamId): void {}

    public function parseWebhook(Request $request): ?array
    {
        return null;
    }
}
