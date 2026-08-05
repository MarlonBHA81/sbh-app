<?php

namespace App\Support;

/**
 * Detects whether the ffmpeg/ffprobe binaries are actually available so the
 * processing jobs can degrade gracefully in environments (dev, CI) that lack
 * them, keeping the original upload and marking it ready.
 */
class MediaBinaries
{
    public static function hasFfmpeg(): bool
    {
        return self::exists((string) env('FFMPEG_BINARIES', 'ffmpeg'));
    }

    public static function hasFfprobe(): bool
    {
        return self::exists((string) env('FFPROBE_BINARIES', 'ffprobe'));
    }

    private static function exists(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary);
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }
}
