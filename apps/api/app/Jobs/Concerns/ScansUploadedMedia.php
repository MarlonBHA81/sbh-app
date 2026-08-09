<?php

namespace App\Jobs\Concerns;

use App\Contracts\VirusScanner;
use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs a malware scan over an assembled media file at the start of its
 * processing job. On a hit the file (and any thumbnail) is deleted and the
 * media is marked 'infected' so it never gets served or transcoded.
 */
trait ScansUploadedMedia
{
    /** Returns true when the media was quarantined (caller should stop). */
    protected function quarantineIfInfected(Media $media, VirusScanner $scanner): bool
    {
        $result = $scanner->scanDiskFile($media->disk, $media->path);

        if (! $result->isInfected()) {
            return false;
        }

        Storage::disk($media->disk)->delete($media->path);
        if ($media->thumb_path) {
            Storage::disk($media->disk)->delete($media->thumb_path);
        }

        $media->forceFill(['status' => Media::STATUS_INFECTED])->save();

        Log::warning('Upload quarantined by virus scan', [
            'media' => $media->ulid,
            'signature' => $result->signature,
        ]);

        return true;
    }
}
