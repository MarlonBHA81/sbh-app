<?php

namespace App\Jobs;

use App\Contracts\VirusScanner;
use App\Jobs\Concerns\ScansUploadedMedia;
use App\Models\Media;
use App\Support\MediaBinaries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

/**
 * Probes an uploaded audio file for its duration when ffprobe is available and
 * marks it ready. The original file is always kept as-is.
 */
class ProcessAudioUpload implements ShouldQueue
{
    use Queueable, ScansUploadedMedia;

    public function __construct(public Media $media) {}

    public function handle(VirusScanner $scanner): void
    {
        $media = $this->media->fresh();

        if (! $media || $media->status !== Media::STATUS_PROCESSING) {
            return;
        }

        if ($this->quarantineIfInfected($media, $scanner)) {
            return;
        }

        $duration = null;

        if (MediaBinaries::hasFfprobe()) {
            try {
                $duration = FFMpeg::fromDisk($media->disk)->open($media->path)->getDurationInSeconds();
            } catch (Throwable $e) {
                Log::warning('Audio probe failed; marking ready without duration', [
                    'media' => $media->ulid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $media->forceFill([
            'duration_seconds' => $duration,
            'status' => Media::STATUS_READY,
        ])->save();
    }
}
