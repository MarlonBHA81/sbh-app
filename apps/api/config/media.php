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

];
