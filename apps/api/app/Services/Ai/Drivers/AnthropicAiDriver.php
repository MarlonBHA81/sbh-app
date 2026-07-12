<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gateway backed by the Anthropic Claude Messages API. It asks the model for a
 * strict JSON response and parses it defensively; any transport, HTTP or
 * decoding failure is swallowed and logged, returning the same safe defaults as
 * the null driver so a provider outage never breaks a user request.
 */
class AnthropicAiDriver implements AiGateway
{
    /** Policy categories the moderation prompt is constrained to. */
    private const CATEGORIES = [
        'spam', 'harassment', 'hate_speech', 'violence',
        'misinformation', 'nudity', 'scam', 'self_harm', 'other',
    ];

    /**
     * @param  array<string, mixed>  $config  The config('ai.anthropic') array.
     */
    public function __construct(private readonly array $config) {}

    public function enabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

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

    /**
     * Issue a Messages API call and return the decoded JSON payload the model
     * emitted, or null on any failure.
     *
     * @return array<string, mixed>|null
     */
    private function request(string $system, string $userText, int $maxTokens): ?array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com'), '/'))
                ->post('/v1/messages', [
                    'model' => $this->config['model'] ?? 'claude-haiku-4-5-20251001',
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => [
                        ['role' => 'user', 'content' => $userText],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Anthropic request failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('content.0.text');

            if (! is_string($text)) {
                return null;
            }

            return $this->decodeJson($text);
        } catch (Throwable $e) {
            Log::warning('Anthropic request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Extract the first JSON object from the model's text output.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
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
