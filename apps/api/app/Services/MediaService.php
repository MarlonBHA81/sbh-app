<?php

namespace App\Services;

use App\Contracts\VirusScanner;
use App\Models\Media;
use App\Models\Profile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class MediaService
{
    public function __construct(private readonly VirusScanner $scanner) {}

    /**
     * Reject an uploaded file that fails a malware scan before it is stored or
     * processed. A no-op when scanning is disabled (the Null scanner).
     */
    private function guardUpload(UploadedFile $file): void
    {
        if ($this->scanner->rejects($this->scanner->scanLocalFile($file->getRealPath()))) {
            throw ValidationException::withMessages([
                'file' => [__('This file failed a security scan and was not uploaded.')],
            ]);
        }
    }

    /**
     * Process and store an uploaded image for the given profile.
     *
     * The image is converted to WebP, scaled down to the configured maximum
     * width (aspect ratio preserved) and a thumbnail is generated alongside.
     */
    public function storeImage(Profile $profile, UploadedFile $file): Media
    {
        $this->guardUpload($file);

        $encoder = new WebpEncoder(quality: config('media.webp_quality'));

        // orient() applies the EXIF orientation flag then drops it, so rotated
        // phone photos are stored upright (the WebP re-encode also strips the
        // rest of the EXIF, including GPS).
        $image = Image::decodePath($file->getRealPath())
            ->orient()
            ->scaleDown(width: config('media.max_width'));
        $encoded = $image->encode($encoder);

        $thumb = Image::decodePath($file->getRealPath())
            ->orient()
            ->scaleDown(width: config('media.thumb_width'))
            ->encode($encoder);

        $ulid = (string) Str::ulid();
        $directory = 'media/'.$profile->ulid;
        $path = "{$directory}/{$ulid}.webp";
        $thumbPath = "{$directory}/{$ulid}_thumb.webp";

        $disk = Storage::disk(config('media.public_disk'));
        $disk->put($path, (string) $encoded);
        $disk->put($thumbPath, (string) $thumb);

        return Media::create([
            'ulid' => $ulid,
            'profile_id' => $profile->id,
            'type' => Media::TYPE_IMAGE,
            'disk' => config('media.public_disk'),
            'path' => $path,
            'thumb_path' => $thumbPath,
            'width' => $image->width(),
            'height' => $image->height(),
            'size_bytes' => strlen((string) $encoded),
            'mime' => 'image/webp',
            'status' => Media::STATUS_READY,
        ]);
    }

    /** Output size of a stored avatar (square), in px. */
    private const AVATAR_SIZE = 512;

    /** Output size of a stored cover banner (3:1), in px. */
    private const COVER_WIDTH = 1500;

    private const COVER_HEIGHT = 500;

    /**
     * Process and store a profile avatar: cover-fit to a 512×512 WebP square.
     * Replaces (and deletes) any previous avatar. Returns the stored path.
     */
    public function storeAvatar(Profile $profile, UploadedFile $file): string
    {
        $this->guardUpload($file);

        $encoded = Image::decodePath($file->getRealPath())
            ->cover(self::AVATAR_SIZE, self::AVATAR_SIZE)
            ->encode(new WebpEncoder(quality: config('media.webp_quality')));

        // A unique filename per upload guarantees a fresh URL when the photo is
        // replaced, so browsers (and the <img> src) never serve the cached old
        // image. The previous file is deleted in replaceProfileImage().
        $path = 'media/avatars/'.$profile->ulid.'-'.Str::lower(Str::random(10)).'.webp';

        $this->replaceProfileImage($profile, 'avatar_path', $path, (string) $encoded);

        return $path;
    }

    /**
     * Process and store a business cover banner: cover-fit to a 1500×500 WebP
     * (3:1). Replaces any previous cover. Returns the stored path.
     */
    public function storeCover(Profile $profile, UploadedFile $file): string
    {
        $this->guardUpload($file);

        $encoded = Image::decodePath($file->getRealPath())
            ->cover(self::COVER_WIDTH, self::COVER_HEIGHT)
            ->encode(new WebpEncoder(quality: config('media.webp_quality')));

        $path = 'media/covers/'.$profile->ulid.'-'.Str::lower(Str::random(10)).'.webp';

        $this->replaceProfileImage($profile, 'cover_path', $path, (string) $encoded);

        return $path;
    }

    /** Remove a stored profile image (avatar or cover) and clear the column. */
    public function removeProfileImage(Profile $profile, string $column): void
    {
        $existing = $profile->getAttribute($column);

        if ($existing) {
            Storage::disk(config('media.public_disk'))->delete($existing);
        }

        $profile->forceFill([$column => null])->save();
    }

    /**
     * Write the encoded bytes, delete the previous file if the path changed,
     * and persist the column. The public disk is configured with
     * `'throw' => false`, so a failed write (e.g. a root-owned storage volume
     * php-fpm can't write to) returns false instead of raising — which would
     * silently leave the DB pointing at a file that never landed and serve a
     * broken image forever. Guard on the return value so the failure surfaces
     * as a 500 and the DB column is only updated once the bytes are on disk.
     */
    private function replaceProfileImage(Profile $profile, string $column, string $path, string $bytes): void
    {
        $previous = $profile->getAttribute($column);

        $disk = config('media.public_disk');

        if (Storage::disk($disk)->put($path, $bytes) === false) {
            throw new \RuntimeException(
                "Failed to write image to the [{$disk}] disk at [{$path}] — check that ".
                'the configured public media disk is writable by the web server user.'
            );
        }

        if ($previous && $previous !== $path) {
            Storage::disk($disk)->delete($previous);
        }

        $profile->forceFill([$column => $path])->save();
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
