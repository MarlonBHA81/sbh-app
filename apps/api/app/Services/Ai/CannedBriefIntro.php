<?php

namespace App\Services\Ai;

use App\Models\Profile;

/**
 * Deterministic, honest fallback headline for the Daily Business Brief when no
 * AI provider is configured (the null driver) or a live call fails. Uses only
 * the member's real profile fields — never fabricated data — and stays a single
 * encouraging line.
 */
class CannedBriefIntro
{
    /**
     * Build a short personalised headline for the member's brief.
     */
    public static function generate(Profile $profile): string
    {
        $name = trim((string) $profile->name);
        $firstName = $name !== '' ? explode(' ', $name)[0] : null;
        $industry = trim((string) $profile->category);
        $stage = trim((string) $profile->journey_stage);

        $greeting = $firstName !== null
            ? "Morning, {$firstName} —"
            : 'Your daily brief —';

        $focus = match (true) {
            $industry !== '' => "here's what's worth a look in {$industry} today.",
            $stage !== '' => 'here are a few things to move your business forward while '
                .str_replace('_', ' ', $stage).'.',
            default => "here are a few things worth your attention today.",
        };

        return "{$greeting} {$focus}";
    }
}
