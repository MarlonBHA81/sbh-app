<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Profile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class MediaService
{
    /**
     * Process and store an uploaded image for the given profile.
     *
     * The image is converted to WebP, scaled down to the configured maximum
     * width (aspect ratio preserved) and a thumbnail is generated alongside.
     */
    public function storeImage(Profile $profile, UploadedFile $file): Media
    {
        $encoder = new WebpEncoder(quality: config('media.webp_quality'));

        $image = Image::decodePath($file->getRealPath())
            ->scaleDown(width: config('media.max_width'));
        $encoded = $image->encode($encoder);

        $thumb = Image::decodePath($file->getRealPath())
            ->scaleDown(width: config('media.thumb_width'))
            ->encode($encoder);

        $ulid = (string) Str::ulid();
        $directory = 'media/'.$profile->ulid;
        $path = "{$directory}/{$ulid}.webp";
        $thumbPath = "{$directory}/{$ulid}_thumb.webp";

        $disk = Storage::disk('public');
        $disk->put($path, (string) $encoded);
        $disk->put($thumbPath, (string) $thumb);

        return Media::create([
            'ulid' => $ulid,
            'profile_id' => $profile->id,
            'type' => Media::TYPE_IMAGE,
            'disk' => 'public',
            'path' => $path,
            'thumb_path' => $thumbPath,
            'width' => $image->width(),
            'height' => $image->height(),
            'size_bytes' => strlen((string) $encoded),
            'mime' => 'image/webp',
            'status' => Media::STATUS_READY,
        ]);
    }

    /**
     * Generate a WebP thumbnail for a video from an extracted poster frame and
     * store it alongside the media on its disk. Returns the stored thumb path.
     */
    public function storeThumbFromFrame(Media $media, string $framePath): string
    {
        $encoder = new WebpEncoder(quality: config('media.webp_quality'));

        $thumb = Image::decodePath($framePath)
            ->scaleDown(width: config('media.thumb_width'))
            ->encode($encoder);

        $thumbPath = 'media/'.$media->profile->ulid.'/'.$media->ulid.'_thumb.webp';

        Storage::disk($media->disk)->put($thumbPath, (string) $thumb);

        return $thumbPath;
    }
}
