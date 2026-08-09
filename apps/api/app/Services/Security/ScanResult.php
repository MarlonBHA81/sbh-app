<?php

namespace App\Services\Security;

/**
 * The verdict of a malware scan. `clean`/`infected` are real verdicts;
 * `disabled` means scanning is switched off; `unavailable` means the scanner
 * was asked but couldn't produce a verdict (unreachable, timeout, file too
 * large for the stream limit) — those are subject to the fail-closed policy.
 */
final class ScanResult
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const DISABLED = 'disabled';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $outcome,
        public readonly ?string $signature = null,
        public readonly ?string $reason = null,
    ) {}

    public static function clean(): self
    {
        return new self(self::CLEAN);
    }

    public static function infected(string $signature): self
    {
        return new self(self::INFECTED, signature: $signature);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED);
    }

    public static function unavailable(string $reason): self
    {
        return new self(self::UNAVAILABLE, reason: $reason);
    }

    public function isClean(): bool
    {
        return $this->outcome === self::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->outcome === self::INFECTED;
    }

    public function isUnavailable(): bool
    {
        return $this->outcome === self::UNAVAILABLE;
    }

    /** Whether an actual verdict (clean or infected) was reached. */
    public function wasScanned(): bool
    {
        return $this->outcome === self::CLEAN || $this->outcome === self::INFECTED;
    }
}
