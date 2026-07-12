<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;

/**
 * No-op gateway used when AI is disabled (the default). Every method returns a
 * safe empty result and performs no I/O.
 */
class NullAiDriver implements AiGateway
{
    public function moderateText(string $text): ?AiModerationResult
    {
        return null;
    }

    public function suggestTopics(string $text, int $max = 3): array
    {
        return [];
    }

    public function enabled(): bool
    {
        return false;
    }
}
