<?php

namespace App\Services\Ai;

/**
 * Immutable result of an AI moderation assessment. This is advisory only — a
 * human moderator makes the final call; the assessment merely surfaces in the
 * admin queue to help prioritise.
 */
class AiModerationResult
{
    /**
     * @param  list<string>  $categories  Policy categories the model flagged.
     * @param  float  $confidence  Model confidence in [0, 1].
     */
    public function __construct(
        public readonly bool $flagged,
        public readonly array $categories = [],
        public readonly float $confidence = 0.0,
        public readonly ?string $summary = null,
    ) {}

    /**
     * @return array{flagged: bool, categories: list<string>, confidence: float, summary: string|null}
     */
    public function toArray(): array
    {
        return [
            'flagged' => $this->flagged,
            'categories' => $this->categories,
            'confidence' => $this->confidence,
            'summary' => $this->summary,
        ];
    }
}
