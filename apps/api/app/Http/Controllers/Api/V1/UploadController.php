<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Profile;
use App\Models\UploadSession;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UploadController extends Controller
{
    public function __construct(private UploadService $uploads) {}

    /**
     * Initialise a chunked upload session.
     */
    public function store(InitUploadRequest $request): JsonResponse
    {
        $profile = $this->profile($request);

        $session = $this->uploads->init($profile, [
            'filename' => $request->string('filename')->value(),
            'mime' => $request->string('mime')->value(),
            'media_type' => $request->string('type')->value(),
            'size_bytes' => (int) $request->integer('size_bytes'),
        ]);

        return response()->json([
            'data' => [
                'ulid' => $session->ulid,
                'chunk_size' => (int) config('media.chunk_size'),
                'total_chunks' => $session->total_chunks,
            ],
        ], 201);
    }

    /**
     * Upload a single chunk (raw body or multipart 'chunk' field).
     */
    public function chunk(Request $request, UploadSession $upload, int $index): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        abort_if(
            $index < 0 || $index >= $upload->total_chunks,
            422,
            'Chunk index is out of range.'
        );

        abort_unless(
            in_array($upload->status, [UploadSession::STATUS_PENDING, UploadSession::STATUS_ASSEMBLING], true),
            409,
            'This upload session is no longer accepting chunks.'
        );

        $contents = $request->hasFile('chunk')
            ? $request->file('chunk')
            : $request->getContent(true);

        $this->uploads->storeChunk($upload, $index, $contents);

        $upload->refresh();

        return response()->json([
            'data' => [
                'ulid' => $upload->ulid,
                'received_chunks' => $upload->received_chunks,
                'total_chunks' => $upload->total_chunks,
            ],
        ]);
    }

    /**
     * Assemble the uploaded chunks and create the media row.
     */
    public function complete(Request $request, UploadSession $upload): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        if ($upload->status === UploadSession::STATUS_DONE && $upload->media) {
            return (new MediaResource($upload->media))->response()->setStatusCode(201);
        }

        $media = $this->uploads->complete($upload);

        // On a sync queue the processing job has already run; reflect its result.
        return (new MediaResource($media->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Abort a session and clean up its chunks.
     */
    public function destroy(Request $request, UploadSession $upload): Response
    {
        $this->authorizeOwner($request, $upload);

        $this->uploads->abort($upload);

        return response()->noContent();
    }

    private function profile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 403, 'An active profile is required to upload media.');

        return $profile;
    }

    private function authorizeOwner(Request $request, UploadSession $upload): void
    {
        $profile = $this->profile($request);

        abort_unless($upload->profile_id === $profile->id, 403);
    }
}
