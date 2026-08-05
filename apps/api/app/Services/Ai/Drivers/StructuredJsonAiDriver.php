<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;

/**
 * Shared prompt + parsing logic for providers that answer with a strict JSON
 * object. Concrete drivers implement only enabled() and request() (the raw
 * provider call returning the decoded JSON, or null on any failure).
 */
abstract class StructuredJsonAiDriver implements AiGateway
{
    /** Policy categories the moderation prompt is constrained to. */
    protected const CATEGORIES = [
        'spam', 'harassment', 'hate_speech', 'violence',
        'misinformation', 'nudity', 'scam', 'self_harm', 'other',
    ];

    /**
     * Issue a provider call and return the decoded JSON object the model
     * emitted, or null on any transport/HTTP/decoding failure.
     *
     * @return array<string, mixed>|null
     */
    abstract protected function request(string $system, string $userText, int $maxTokens): ?array;

    public function moderateText(string $text): ?AiModerationResult
    {
        $text = trim($text);

        if (! $this->enabled() || $text === '') {
            return null;
        }

        $system = 'You are a content-moderation classifier for a social platform. '
            .'Assess the user text for policy violations across these categories: '
            .implode(', ', self::CATEGORIES).'. '
            .'Respond with ONLY a JSON object, no prose, of the exact shape: '
            .'{"flagged": boolean, "categories": string[], "confidence": number between 0 and 1, "summary": string}. '
            .'categories must be a subset of the listed categories. '
            .'summary is one short sentence explaining the assessment.';

        $data = $this->request($system, $text, maxTokens: 300);

        if (! is_array($data)) {
            return null;
        }

        $categories = array_values(array_intersect(
            self::CATEGORIES,
            is_array($data['categories'] ?? null) ? $data['categories'] : [],
        ));

        return new AiModerationResult(
            flagged: (bool) ($data['flagged'] ?? false),
            categories: $categories,
            confidence: $this->clampConfidence($data['confidence'] ?? 0),
            summary: isset($data['summary']) && is_string($data['summary']) ? $data['summary'] : null,
        );
    }

    public function suggestTopics(string $text, int $max = 3): array
    {
        $text = trim($text);

        if (! $this->enabled() || $text === '') {
            return [];
        }

        $system = 'You suggest topic tags for social posts. '
            ."Return ONLY a JSON object of the shape {\"slugs\": string[]} with at most {$max} entries. "
            .'Each slug is lowercase, hyphen-separated, 1-3 words (e.g. "small-business", "marketing"). '
            .'Return an empty array if no clear topic applies.';

        $data = $this->request($system, $text, maxTokens: 150);

        if (! is_array($data) || ! is_array($data['slugs'] ?? null)) {
            return [];
        }

        $slugs = [];

        foreach ($data['slugs'] as $slug) {
            if (is_string($slug) && ($clean = $this->normaliseSlug($slug)) !== '') {
                $slugs[] = $clean;
            }
        }

        return array_slice(array_values(array_unique($slugs)), 0, $max);
    }

    public function rankItems(string $context, array $candidates, int $max = 3): array
    {
        if (! $this->enabled() || $candidates === []) {
            return [];
        }

        // Only the whitelisted keys may be returned; index candidate text.
        $allowed = [];
        $lines = [];

        foreach ($candidates as $candidate) {
            $key = (string) ($candidate['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $allowed[$key] = true;
            $label = trim(implode(' — ', array_filter([
                isset($candidate['kind']) ? '['.$candidate['kind'].']' : null,
                isset($candidate['title']) ? (string) $candidate['title'] : null,
                isset($candidate['summary']) ? (string) $candidate['summary'] : null,
            ])));
            $lines[] = "{$key}: {$label}";
        }

        if ($allowed === []) {
            return [];
        }

        $system = 'You curate a short daily business brief for one member of a '
            .'small-business platform. Given the member context and a list of '
            ."candidate items (each `key: description`), pick the {$max} most "
            .'relevant items for THIS member, most relevant first. '
            .'Respond with ONLY a JSON object of the shape {"keys": string[]}. '
            .'Each entry MUST be one of the given keys, no duplicates, at most '
            ."{$max} entries. Prefer items matching the member's industry, "
            .'interests and recent activity.';

        $userText = "MEMBER CONTEXT:\n{$context}\n\nCANDIDATE ITEMS:\n".implode("\n", $lines);

        $data = $this->request($system, $userText, maxTokens: 200);

        if (! is_array($data) || ! is_array($data['keys'] ?? null)) {
            return [];
        }

        $chosen = [];

        foreach ($data['keys'] as $key) {
            $key = is_string($key) ? $key : '';

            if ($key !== '' && isset($allowed[$key]) && ! in_array($key, $chosen, true)) {
                $chosen[] = $key;
            }
        }

        return array_slice($chosen, 0, $max);
    }

    /**
     * Extract the first JSON object from the model's text output.
     *
     * @return array<string, mixed>|null
     */
    protected function decodeJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function clampConfidence(mixed $value): float
    {
        $value = is_numeric($value) ? (float) $value : 0.0;

        return max(0.0, min(1.0, $value));
    }

    private function normaliseSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
