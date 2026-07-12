<?php

namespace Tests\Support;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;

/**
 * Deterministic AiGateway test double so specs never touch a real provider.
 */
class FakeAiGateway implements AiGateway
{
    public function __construct(
        private bool $enabled = true,
        private ?AiModerationResult $moderation = null,
        private array $topics = [],
    ) {}

    public function moderateText(string $text): ?AiModerationResult
    {
        return $this->enabled ? $this->moderation : null;
    }

    public function suggestTopics(string $text, int $max = 3): array
    {
        return $this->enabled ? array_slice($this->topics, 0, $max) : [];
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
