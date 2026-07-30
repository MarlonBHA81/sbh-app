<?php

namespace Tests\Support;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;

/**
 * Deterministic AiGateway test double so specs never touch a real provider.
 */
class FakeAiGateway implements AiGateway
{
    /** @var list<string> Keys returned by rankItems (intersected with candidates). */
    public array $rankedKeys = [];

    /** Number of times rankItems has been called (for caching assertions). */
    public int $rankCalls = 0;

    public function __construct(
        private bool $enabled = true,
        private ?AiModerationResult $moderation = null,
        private array $topics = [],
        private ?string $chatReply = 'A helpful coaching reply.',
    ) {}

    public function moderateText(string $text): ?AiModerationResult
    {
        return $this->enabled ? $this->moderation : null;
    }

    public function suggestTopics(string $text, int $max = 3): array
    {
        return $this->enabled ? array_slice($this->topics, 0, $max) : [];
    }

    public function chat(string $system, array $messages, int $maxTokens = 600): ?string
    {
        return $this->enabled ? $this->chatReply : null;
    }

    public function rankItems(string $context, array $candidates, int $max = 3): array
    {
        $this->rankCalls++;

        if (! $this->enabled) {
            return [];
        }

        $keys = array_column($candidates, 'key');

        // Return the configured ranking, keeping only real candidate keys.
        return array_slice(array_values(array_intersect($this->rankedKeys, $keys)), 0, $max);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
