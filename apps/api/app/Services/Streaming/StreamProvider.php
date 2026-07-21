<?php

namespace App\Services\Streaming;

use Illuminate\Http\Request;

/**
 * A live-streaming provider (RTMP ingest → HLS playback) for masterclass rooms
 * (ask #4). Modeled on the payments driver: swappable, and testable via
 * Http::fake so no live provider account is needed to build.
 */
interface StreamProvider
{
    /** Whether the provider is configured and live streaming can be created. */
    public function enabled(): bool;

    /**
     * Create a live stream. Returns the host ingest credentials + viewer
     * playback details.
     *
     * @return array{provider: string, provider_stream_id: string, stream_key: string, ingest_url: string, playback_id: string, playback_url: string}
     */
    public function createLiveStream(): array;

    /** Tear down a live stream at the provider (best-effort). */
    public function deleteLiveStream(string $providerStreamId): void;

    /**
     * Parse (and verify) a provider webhook. Returns a live-status change and/or
     * a ready recording (replay) keyed to a live stream, or null when the event
     * is invalid/unrecognised.
     *
     * @return array{provider_stream_id: string, status?: string, recording_playback_url?: string}|null
     */
    public function parseWebhook(Request $request): ?array;
}
