<?php

namespace App\Contracts;

use App\Services\Security\ScanResult;

/**
 * Scans uploaded files for malware. Implementations are resolved from config
 * (a no-op scanner when disabled, ClamAV when enabled) so callers never depend
 * on a running clamd — the whole pipeline degrades gracefully.
 */
interface VirusScanner
{
    /** Scan a file on the local filesystem (e.g. a just-received upload temp). */
    public function scanLocalFile(string $absolutePath): ScanResult;

    /** Scan a file already stored on a Laravel filesystem disk. */
    public function scanDiskFile(string $disk, string $path): ScanResult;

    /**
     * Whether a result should block/quarantine the upload. Infected always
     * blocks; an unavailable scanner blocks only when fail-closed is configured.
     */
    public function rejects(ScanResult $result): bool;
}
