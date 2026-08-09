<?php

namespace Tests\Fakes;

use App\Services\Security\AbstractVirusScanner;
use App\Services\Security\ScanResult;

/**
 * A virus scanner whose verdict is fixed up front — lets tests exercise the
 * infected / unavailable paths without a running clamd. The fail-closed policy
 * in rejects() is inherited from the real base class.
 */
class FakeVirusScanner extends AbstractVirusScanner
{
    public function __construct(private readonly ScanResult $verdict) {}

    public static function infected(string $signature = 'Eicar-Test-Signature'): self
    {
        return new self(ScanResult::infected($signature));
    }

    public static function clean(): self
    {
        return new self(ScanResult::clean());
    }

    public static function unavailable(string $reason = 'unreachable'): self
    {
        return new self(ScanResult::unavailable($reason));
    }

    protected function scanStream($stream): ScanResult
    {
        return $this->verdict;
    }
}
