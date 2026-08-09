<?php

namespace App\Services\Security;

use App\Contracts\VirusScanner;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared plumbing for scanners: open a local/disk file as a stream, hand it to
 * the concrete engine, and apply the fail-closed policy. Concrete scanners only
 * implement scanStream().
 */
abstract class AbstractVirusScanner implements VirusScanner
{
    /** Scan an open, readable stream resource and return a verdict. */
    abstract protected function scanStream($stream): ScanResult;

    public function scanLocalFile(string $absolutePath): ScanResult
    {
        $stream = @fopen($absolutePath, 'rb');

        if ($stream === false) {
            return ScanResult::unavailable('unreadable_file');
        }

        try {
            return $this->scanStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function scanDiskFile(string $disk, string $path): ScanResult
    {
        try {
            $stream = Storage::disk($disk)->readStream($path);
        } catch (Throwable) {
            return ScanResult::unavailable('unreadable_file');
        }

        if (! is_resource($stream)) {
            return ScanResult::unavailable('unreadable_file');
        }

        try {
            return $this->scanStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function rejects(ScanResult $result): bool
    {
        if ($result->isInfected()) {
            return true;
        }

        if ($result->isUnavailable()) {
            return (bool) config('services.clamav.fail_closed', false);
        }

        return false;
    }
}
