<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModerationResult;
use App\Services\Ai\CannedCoachReply;

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

    public function chat(string $system, array $messages, int $maxTokens = 600): ?string
    {
        // Works with no API key: return a helpful, honest canned reply built
        // from the member's most recent message.
        $last = '';

        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? null) === 'user') {
                $last = (string) ($message['content'] ?? '');
                break;
            }
        }

        return CannedCoachReply::generate($last);
    }

    public function rankItems(string $context, array $candidates, int $max = 3): array
    {
        // No AI: let the caller apply its own (industry-match) ordering.
        return [];
    }

    public function enabled(): bool
    {
        return false;
    }
}
