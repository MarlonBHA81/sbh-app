<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disks
    |--------------------------------------------------------------------------
    |
    | Which filesystem disk backs public (world-readable media: images, posters,
    | assembled video/audio) versus private (buyer-gated deliverables, in-flight
    | upload chunks) storage. Defaults preserve the historical hardcoded
    | behaviour (`public` / `local`). Point these at `s3` — a disk already
    | defined in config/filesystems.php — to move media to object storage with
    | no code changes; the `disk` recorded on each Media row is taken from
    | `public_disk`, so Media::url() keeps resolving against the right disk.
    |
    */

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),

    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),

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
    | Vendor deliverables (private disk)
    |--------------------------------------------------------------------------
    |
    | Product download files and course lesson attachments are uploaded by
    | vendors and served to buyers. These previously validated size only, which
    | allowed arbitrary executables/scripts to be distributed through the store.
    |
    | Extensions are matched by Laravel's `mimes:` rule, which checks the file's
    | detected MIME type against the extension — not just the filename — so a
    | renamed .exe is rejected.
    |
    | Note: `zip` is intentionally allowed because it is the primary legitimate
    | format for a digital download, and an archive can contain anything. An
    | extension allow-list bounds the obvious cases; it is not a substitute for
    | content scanning, which remains the real control for archive payloads.
    |
    | Keep nginx's client_max_body_size >= deliverable_max_kb; these are
    | single-request uploads, so nginx rejects oversized bodies before Laravel
    | ever validates them.
    |
    */

    'deliverable_max_kb' => (int) env('MEDIA_DELIVERABLE_MAX_KB', 25600),

    'deliverable_mimes' => env(
        'MEDIA_DELIVERABLE_MIMES',
        'pdf,epub,zip,doc,docx,xls,xlsx,ppt,pptx,csv,txt,rtf,odt,ods,'.
        // svg is deliberately excluded: it can carry script, and it offers
        // nothing here that png/webp/pdf don't.
        'png,jpg,jpeg,webp,gif,mp3,m4a,wav,mp4,mov,webm'
    ),

    /*
    |--------------------------------------------------------------------------
    | Business-verification documents (private disk)
    |--------------------------------------------------------------------------
    |
    | ID / CIPC / B-BBEE documents a business submits to be verified. Stored on
    | the private disk and only ever streamed to an admin reviewer. Restricted to
    | document + image formats (no archives/executables) since a reviewer only
    | needs to read them.
    |
    */

    'verification_max_kb' => (int) env('MEDIA_VERIFICATION_MAX_KB', 10240),

    'verification_mimes' => env(
        'MEDIA_VERIFICATION_MIMES',
        'pdf,png,jpg,jpeg,webp'
    ),

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
