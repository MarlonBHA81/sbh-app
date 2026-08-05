<?php

namespace App\Observability;

use Illuminate\Http\Request;

/**
 * Normalised alert extracted from an inbound monitor webhook. Sentry's issue
 * alert payload is the primary shape, but any monitor can post {fingerprint,
 * title, message, level, url}; the fingerprint is what de-duplicates issues.
 */
final class Alert
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $title,
        public readonly string $body,
        public readonly string $level = 'error',
        public readonly ?string $url = null,
        public readonly ?string $environment = null,
    ) {}

    /**
     * Build from a decoded webhook body. Understands Sentry's issue-alert
     * envelope ({data:{event, triggered_rule}}) and falls back to flat keys.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromWebhook(array $payload): self
    {
        // Sentry wraps the event under data.event; flat monitors send top level.
        $event = $payload['data']['event'] ?? $payload['event'] ?? $payload;

        $title = (string) (
            $event['title']
            ?? $event['message']
            ?? $payload['message']
            ?? 'Unlabelled alert'
        );

        $level = (string) ($event['level'] ?? $payload['level'] ?? 'error');
        $environment = isset($event['environment']) ? (string) $event['environment'] : null;
        $url = $event['web_url'] ?? $event['url'] ?? $payload['url'] ?? null;
        $url = $url !== null ? (string) $url : null;

        // Prefer the monitor's own grouping id so our issue tracks the same
        // group; otherwise derive a stable hash from title + culprit.
        $rawFingerprint = $event['issue_id']
            ?? $event['id']
            ?? $payload['fingerprint']
            ?? ($title.'|'.($event['culprit'] ?? ''));

        $fingerprint = substr(hash('sha256', (string) $rawFingerprint), 0, 32);

        $culprit = isset($event['culprit']) ? (string) $event['culprit'] : null;
        $body = trim(($culprit !== null ? "Culprit: {$culprit}\n\n" : '').$title);

        return new self($fingerprint, $title, $body, $level, $url, $environment);
    }

    /**
     * Convenience builder straight from the request JSON body.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromWebhook($request->json()->all());
    }
}
