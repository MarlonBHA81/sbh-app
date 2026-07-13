<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gateway backed by the Anthropic Claude Messages API. It asks the model for a
 * strict JSON response and parses it defensively; any transport, HTTP or
 * decoding failure is swallowed and logged, returning the same safe defaults as
 * the null driver so a provider outage never breaks a user request.
 */
class AnthropicAiDriver extends StructuredJsonAiDriver
{
    /**
     * @param  array<string, mixed>  $config  The config('ai.anthropic') array.
     */
    public function __construct(private readonly array $config) {}

    public function enabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function request(string $system, string $userText, int $maxTokens): ?array
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
}
