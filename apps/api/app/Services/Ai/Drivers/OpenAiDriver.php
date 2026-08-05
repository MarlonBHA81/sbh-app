<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gateway backed by the OpenAI Chat Completions API. Same contract as the
 * Anthropic driver: strict-JSON prompts, defensive parsing, and any failure
 * degrades to the null driver's safe defaults.
 */
class OpenAiDriver extends StructuredJsonAiDriver
{
    /**
     * @param  array<string, mixed>  $config  The config('ai.openai') array.
     */
    public function __construct(private readonly array $config) {}

    public function enabled(): bool
    {
        return ! empty($this->config['api_key']);
    }

    public function chat(string $system, array $messages, int $maxTokens = 600): ?string
    {
        // BACKEND-TODO: live AI Coach replies require OPENAI_API_KEY and
        // AI_DRIVER=openai (see config/ai.php). Without them the null driver
        // serves canned coaching replies instead.
        if (! $this->enabled()) {
            return null;
        }

        $payload = [['role' => 'system', 'content' => $system]];

        foreach ($messages as $message) {
            $role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($message['content'] ?? ''));

            if ($content !== '') {
                $payload[] = ['role' => $role, 'content' => $content];
            }
        }

        try {
            $response = Http::withToken((string) $this->config['api_key'])
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com'), '/'))
                ->post('/v1/chat/completions', [
                    'model' => $this->config['model'] ?? 'gpt-4o-mini',
                    'max_tokens' => $maxTokens,
                    'messages' => $payload,
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI chat failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('OpenAI chat threw', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function request(string $system, string $userText, int $maxTokens): ?array
    {
        try {
            $response = Http::withToken((string) $this->config['api_key'])
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com'), '/'))
                ->post('/v1/chat/completions', [
                    'model' => $this->config['model'] ?? 'gpt-4o-mini',
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userText],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI request failed', ['status' => $response->status()]);

                return null;
            }

            $text = $response->json('choices.0.message.content');

            if (! is_string($text)) {
                return null;
            }

            return $this->decodeJson($text);
        } catch (Throwable $e) {
            Log::warning('OpenAI request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
