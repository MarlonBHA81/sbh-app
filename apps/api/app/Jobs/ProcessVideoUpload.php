<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\MediaService;
use App\Support\MediaBinaries;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

/**
 * Transcodes an uploaded video to a 720p-capped H.264/AAC MP4, extracts a
 * poster frame and records dimensions/duration. When ffmpeg is unavailable
 * (or fails), the original file is kept and simply marked ready — the frontend
 * polls GET /media/{ulid} for the status either way.
 */
class ProcessVideoUpload implements ShouldQueue
{
    use Queueable;

    public function __construct(public Media $media) {}

    public function handle(MediaService $mediaService): void
    {
        $media = $this->media->fresh();

        if (! $media || $media->status !== Media::STATUS_PROCESSING) {
            return;
        }

        if (! MediaBinaries::hasFfmpeg()) {
            Log::warning('ffmpeg unavailable; keeping original video upload', ['media' => $media->ulid]);
            $media->forceFill(['status' => Media::STATUS_READY, 'thumb_path' => null])->save();

            return;
        }

        try {
            $this->transcode($media, $mediaService);
        } catch (Throwable $e) {
            Log::warning('Video processing failed; keeping original', [
                'media' => $media->ulid,
                'error' => $e->getMessage(),
            ]);
            $media->forceFill(['status' => Media::STATUS_READY, 'thumb_path' => null])->save();
        }
    }

    private function transcode(Media $media, MediaService $mediaService): void
    {
        $maxHeight = (int) config('media.video.max_height');
        $crf = (int) config('media.video.crf');

        $opened = FFMpeg::fromDisk($media->disk)->open($media->path);

        $duration = $opened->getDurationInSeconds();
        $stream = $opened->getVideoStream();
        $width = $stream?->get('width');
        $height = $stream?->get('height');

        // Cap the longest edge to 720p while preserving aspect ratio.
        if ($height && $height > $maxHeight) {
            $scaledWidth = (int) round(($width / $height) * $maxHeight / 2) * 2;
            $opened->addFilter(new ResizeFilter(new Dimension($scaledWidth, $maxHeight)));
            $width = $scaledWidth;
            $height = $maxHeight;
        }

        $format = (new X264('aac', 'libx264'))
            ->setAdditionalParameters(['-crf', (string) $crf, '-preset', 'medium']);

        $newPath = preg_replace('/\.[^.]+$/', '.mp4', $media->path);

        $opened->export()->toDisk($media->disk)->inFormat($format)->save($newPath);

        // Poster frame -> webp thumbnail via the image pipeline.
        $thumbPath = null;
        $frameTmp = tempnam(sys_get_temp_dir(), 'poster').'.jpg';

        try {
            FFMpeg::fromDisk($media->disk)->open($media->path)
                ->getFrameFromSeconds((float) config('media.video.poster_second'))
                ->export()
                ->save($frameTmp);

            $thumbPath = $mediaService->storeThumbFromFrame($media, $frameTmp);
        } finally {
            @unlink($frameTmp);
        }

        if ($newPath !== $media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->forceFill([
            'path' => $newPath,
            'mime' => 'video/mp4',
            'thumb_path' => $thumbPath,
            'width' => $width,
            'height' => $height,
            'duration_seconds' => $duration,
            'size_bytes' => Storage::disk($media->disk)->size($newPath),
            'status' => Media::STATUS_READY,
        ])->save();
    }
}
