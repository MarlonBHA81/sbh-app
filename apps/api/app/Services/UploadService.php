<?php

namespace App\Services;

use App\Jobs\ProcessAudioUpload;
use App\Jobs\ProcessVideoUpload;
use App\Models\Media;
use App\Models\Profile;
use App\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadService
{
    /** Whitelisted upload MIME types keyed by media type. */
    public const MIMES = [
        Media::TYPE_VIDEO => ['video/mp4', 'video/webm', 'video/quicktime'],
        Media::TYPE_AUDIO => ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg'],
    ];

    /** MIME -> file extension for the assembled file. */
    private const EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
    ];

    /**
     * Initialise a chunked upload session for the given profile.
     *
     * @param  array{filename:string, mime:string, media_type:string, size_bytes:int}  $data
     */
    public function init(Profile $profile, array $data): UploadSession
    {
        $chunkSize = (int) config('media.chunk_size');

        return UploadSession::create([
            'profile_id' => $profile->id,
            'filename' => $data['filename'],
            'mime' => $data['mime'],
            'media_type' => $data['media_type'],
            'size_bytes' => $data['size_bytes'],
            'total_chunks' => (int) max(1, ceil($data['size_bytes'] / $chunkSize)),
            'status' => UploadSession::STATUS_PENDING,
        ]);
    }

    /**
     * Persist a single chunk. Idempotent: re-uploading the same index is a
     * no-op and does not double-count received chunks.
     *
     * @param  string|resource|UploadedFile  $contents
     */
    public function storeChunk(UploadSession $session, int $index, mixed $contents): void
    {
        $disk = Storage::disk(config('media.private_disk'));
        $path = $session->chunkDirectory().'/'.$index;

        $alreadyStored = $disk->exists($path);

        if ($contents instanceof UploadedFile) {
            $disk->putFileAs($session->chunkDirectory(), $contents, (string) $index);
        } else {
            $disk->put($path, $contents);
        }

        if (! $alreadyStored) {
            $session->increment('received_chunks');
        }
    }

    /**
     * Verify all chunks are present, assemble them into a single media file
     * (streamed, never fully buffered), create the Media row in the
     * 'processing' state and dispatch the relevant processing job.
     */
    public function complete(UploadSession $session): Media
    {
        $disk = Storage::disk(config('media.private_disk'));
        $dir = $session->chunkDirectory();

        for ($i = 0; $i < $session->total_chunks; $i++) {
            if (! $disk->exists("{$dir}/{$i}")) {
                throw ValidationException::withMessages([
                    'chunks' => ["Chunk {$i} is missing; upload is incomplete."],
                ]);
            }
        }

        $session->update(['status' => UploadSession::STATUS_ASSEMBLING]);

        $ulid = (string) Str::ulid();
        $ext = self::EXTENSIONS[$session->mime] ?? 'bin';
        $finalPath = 'media/'.$session->profile->ulid."/{$ulid}.{$ext}";

        $size = $this->assemble($session, $finalPath);

        $media = Media::create([
            'ulid' => $ulid,
            'profile_id' => $session->profile_id,
            'type' => $session->media_type,
            'disk' => config('media.public_disk'),
            'path' => $finalPath,
            'thumb_path' => null,
            'size_bytes' => $size,
            'mime' => $session->mime,
            'status' => Media::STATUS_PROCESSING,
        ]);

        $disk->deleteDirectory($dir);

        $session->update([
            'status' => UploadSession::STATUS_DONE,
            'media_id' => $media->id,
        ]);

        if ($session->media_type === Media::TYPE_VIDEO) {
            ProcessVideoUpload::dispatch($media);
        } else {
            ProcessAudioUpload::dispatch($media);
        }

        return $media;
    }

    /**
     * Abort a session and delete any stored chunks.
     */
    public function abort(UploadSession $session): void
    {
        Storage::disk(config('media.private_disk'))->deleteDirectory($session->chunkDirectory());

        $session->update(['status' => UploadSession::STATUS_ABORTED]);
    }

    /**
     * Stream chunk files into the final destination and return the byte count.
     */
    private function assemble(UploadSession $session, string $finalPath): int
    {
        $local = Storage::disk(config('media.private_disk'));
        $public = Storage::disk(config('media.public_disk'));
        $dir = $session->chunkDirectory();

        $out = fopen('php://temp', 'w+b');

        for ($i = 0; $i < $session->total_chunks; $i++) {
            $in = $local->readStream("{$dir}/{$i}");
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        $size = ftell($out);
        rewind($out);
        $public->writeStream($finalPath, $out);
        fclose($out);

        return (int) $size;
    }
}
