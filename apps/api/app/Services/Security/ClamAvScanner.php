<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;

/**
 * Scans files against a ClamAV daemon (clamd) over TCP using the INSTREAM
 * command, so no shared filesystem with clamd is required. The daemon's
 * StreamMaxLength must be at least `max_bytes`; files larger than that are
 * skipped (reported unavailable) rather than erroring the scan.
 */
class ClamAvScanner extends AbstractVirusScanner
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout,
        private readonly int $maxBytes,
        private readonly int $chunkSize = 8192,
    ) {}

    protected function scanStream($stream): ScanResult
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            Log::warning('clamav unreachable; upload not scanned', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $errstr,
            ]);

            return ScanResult::unavailable('unreachable');
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            fwrite($socket, "zINSTREAM\0");

            $sent = 0;
            while (! feof($stream)) {
                $chunk = fread($stream, $this->chunkSize);
                if ($chunk === '' || $chunk === false) {
                    break;
                }

                $sent += strlen($chunk);
                if ($sent > $this->maxBytes) {
                    // Close the stream out cleanly and treat it as unscanned;
                    // the daemon would otherwise abort with a size-limit error.
                    fwrite($socket, pack('N', 0));
                    Log::warning('clamav: file exceeds stream limit; skipped', [
                        'bytes' => $sent,
                        'max_bytes' => $this->maxBytes,
                    ]);

                    return ScanResult::unavailable('too_large');
                }

                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            // A zero-length chunk signals end-of-stream.
            fwrite($socket, pack('N', 0));

            $response = '';
            while (! feof($socket)) {
                $buffer = fread($socket, 4096);
                if ($buffer === '' || $buffer === false) {
                    break;
                }
                $response .= $buffer;
            }
        } finally {
            fclose($socket);
        }

        return $this->interpret($response);
    }

    /** Map a clamd INSTREAM reply to a verdict. */
    private function interpret(string $response): ScanResult
    {
        $response = trim(str_replace("\0", '', $response));

        if ($response === '') {
            return ScanResult::unavailable('empty_response');
        }

        if (str_contains($response, 'FOUND')) {
            // e.g. "stream: Eicar-Test-Signature FOUND"
            $signature = trim(preg_replace('/^stream:\s*/', '', str_replace('FOUND', '', $response)));

            return ScanResult::infected($signature !== '' ? $signature : 'unknown');
        }

        if (str_ends_with($response, 'OK')) {
            return ScanResult::clean();
        }

        // e.g. "INSTREAM size limit exceeded. ERROR"
        Log::warning('clamav returned an error response', ['response' => $response]);

        return ScanResult::unavailable('scanner_error');
    }
}
