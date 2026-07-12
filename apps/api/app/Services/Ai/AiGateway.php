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
     * Whether AI features are active (a driver other than null with any
     * required credentials present).
     */
    public function enabled(): bool;
}
