<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerificationDocument;
use App\Support\Moderation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-only streaming of a submitted verification document from the private
 * disk. Routed behind the `admin` middleware so members can never reach another
 * business's ID/CIPC/B-BBEE files; each access is recorded for the audit trail.
 */
class VerificationAdminController extends Controller
{
    public function downloadDocument(BusinessVerificationDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        Moderation::log('verification.document_viewed', $document->verification, [
            'document_type' => $document->type,
        ]);

        return $disk->download($document->path, $document->original_name ?? 'document');
    }
}
