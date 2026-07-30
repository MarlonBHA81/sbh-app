<?php

namespace App\Services\Ai;

/**
 * Provider-agnostic gateway for the platform's AI features. Concrete drivers
 * live in App\Services\Ai\Drivers and are bound in AppServiceProvider by the
 * config('ai.driver') value. Callers must always tolerate a disabled gateway
 * (null / empty results) so AI remains a strictly optional enhancement.
 */
interface AiGateway
{
    /**
     * Assess a piece of user text for policy violations. Returns null when the
     * gateway is disabled or the provider call fails — never throws.
     */
    public function moderateText(string $text): ?AiModerationResult;

    /**
     * Suggest up to $max topic slugs for the given text. Returns an empty array
     * when the gateway is disabled or the provider call fails — never throws.
     *
     * @return list<string>
     */
    public function suggestTopics(string $text, int $max = 3): array;

    /**
     * Free-form chat completion for the AI Coach. Returns the assistant's
     * plain-text reply. The null driver returns a helpful canned reply so the
     * coach works with no API key; live drivers return null on any failure so
     * the caller can fall back gracefully.
     *
     * @param  list<array{role: string, content: string}>  $messages  Prior turns.
     */
    public function chat(string $system, array $messages, int $maxTokens = 600): ?string;

    /**
     * Rank candidate items for one member and return the chosen item keys in
     * priority order (a subset of the candidates' keys). Used to personalise
     * the Daily Brief per member. Returns an empty array when the gateway is
     * disabled or the call fails, so the caller falls back to its own ordering.
     *
     * @param  string  $context  The member's context (industry, interests, activity).
     * @param  list<array{key: string, kind?: string, title?: string, summary?: string}>  $candidates
     * @return list<string> Chosen keys, best first, at most $max.
     */
    public function rankItems(string $context, array $candidates, int $max = 3): array;

    /**
     * Whether AI features are active (a driver other than null with any
     * required credentials present).
     */
    public function enabled(): bool;
}
