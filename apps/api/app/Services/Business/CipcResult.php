<?php

namespace App\Services\Business;

/**
 * The outcome of a CIPC registration lookup. `verified` means CIPC confirmed
 * the registration; `not_found` means it doesn't exist; `unavailable` means the
 * lookup couldn't be performed (disabled, unreachable, no registration number).
 */
final class CipcResult
{
    public const VERIFIED = 'verified';

    public const NOT_FOUND = 'not_found';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly ?string $registeredName = null,
        public readonly ?string $companyStatus = null,
        public readonly ?string $reason = null,
    ) {}

    public static function verified(?string $registeredName = null, ?string $companyStatus = null): self
    {
        return new self(self::VERIFIED, $registeredName, $companyStatus);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function unavailable(string $reason): self
    {
        return new self(self::UNAVAILABLE, reason: $reason);
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }
}
