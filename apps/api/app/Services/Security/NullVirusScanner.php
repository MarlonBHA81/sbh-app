<?php

namespace App\Services\Security;

/**
 * The scanner used when virus scanning is disabled (local, tests, or any prod
 * without a clamd sidecar). Every file comes back as "disabled" — never
 * blocking an upload.
 */
class NullVirusScanner extends AbstractVirusScanner
{
    protected function scanStream($stream): ScanResult
    {
        return ScanResult::disabled();
    }
}
