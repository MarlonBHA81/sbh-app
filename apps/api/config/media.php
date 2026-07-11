<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image processing
    |--------------------------------------------------------------------------
    |
    | Uploaded images are converted to WebP at the configured quality and
    | scaled down to the configured maximum width (aspect ratio preserved).
    | A thumbnail capped at thumb_width is generated alongside the original.
    |
    */

    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),

    'max_width' => (int) env('MEDIA_MAX_WIDTH', 1920),

    'thumb_width' => (int) env('MEDIA_THUMB_WIDTH', 480),

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Chunked video/audio uploads
    |--------------------------------------------------------------------------
    |
    | Large media (video/audio) is uploaded in fixed-size chunks and then
    | assembled server-side. The per-type ceilings below (in megabytes) cap
    | the total upload size accepted when an upload session is initialised.
    |
    */

    'chunk_size' => 1048576, // 1 MiB

    'max_video_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 500),

    'max_audio_mb' => (int) env('MEDIA_MAX_AUDIO_MB', 50),

    'video' => [
        // Cap transcoded output to 720p (height), preserving aspect ratio.
        'max_height' => (int) env('MEDIA_VIDEO_MAX_HEIGHT', 720),
        'crf' => (int) env('MEDIA_VIDEO_CRF', 26),
        'poster_second' => 1,
    ],

];
