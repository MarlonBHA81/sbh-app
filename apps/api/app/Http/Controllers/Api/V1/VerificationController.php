<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\VirusScanner;
use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Models\Profile;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Member-facing business verification: submit ID / CIPC / B-BBEE documents for
 * the active business profile and read back the review status. Documents are
 * stored on the private disk and only ever streamed to an admin reviewer.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly VirusScanner $scanner) {}

    /** The active business profile's latest verification (null if none). */
    public function show(Request $request): JsonResponse
    {
        $profile = $this->businessProfile($request);

        $latest = BusinessVerification::query()
            ->where('profile_id', $profile->id)
            ->with('documents:id,business_verification_id,type,original_name')
            ->latest('id')
            ->first();

        return response()->json(['data' => $latest === null ? null : $this->present($latest)]);
    }

    /** Submit a verification request with documents. */
    public function store(Request $request): JsonResponse
    {
        $profile = $this->businessProfile($request);

        // One open (or approved) submission at a time — a rejected business may
        // resubmit.
        $existing = BusinessVerification::query()
            ->where('profile_id', $profile->id)
            ->whereIn('status', BusinessVerification::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'profile' => [
                    $existing->status === BusinessVerification::STATUS_APPROVED
                        ? 'This business is already verified.'
                        : 'A verification request is already in progress.',
                ],
            ]);
        }

        $rules = [
            'legal_name' => ['required', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
        ];
        $fileRule = ['file', 'mimes:'.config('media.verification_mimes'), 'max:'.config('media.verification_max_kb')];
        $validated = $request->validate($rules + [
            'id_document' => array_merge(['required'], $fileRule),
            'cipc_document' => array_merge(['required'], $fileRule),
            'bbee_document' => array_merge(['nullable'], $fileRule),
        ]);

        $verification = DB::transaction(function () use ($request, $profile, $validated) {
            $verification = BusinessVerification::create([
                'profile_id' => $profile->id,
                'status' => BusinessVerification::STATUS_PENDING,
                'legal_name' => $validated['legal_name'],
                'registration_number' => $validated['registration_number'] ?? null,
                'submitted_by' => $request->user()->id,
            ]);

            $this->storeDocument($verification, $request->file('id_document'), BusinessVerification::TYPE_ID);
            $this->storeDocument($verification, $request->file('cipc_document'), BusinessVerification::TYPE_CIPC);
            if ($request->hasFile('bbee_document')) {
                $this->storeDocument($verification, $request->file('bbee_document'), BusinessVerification::TYPE_BBEE);
            }

            return $verification;
        });

        Activity::log('verification.submitted', $verification, ['profile_id' => $profile->id]);

        return response()->json(['data' => $this->present($verification->load('documents'))], 201);
    }

    private function storeDocument(BusinessVerification $verification, UploadedFile $file, string $type): void
    {
        if ($this->scanner->rejects($this->scanner->scanLocalFile($file->getRealPath()))) {
            throw ValidationException::withMessages([
                'documents' => [__('One of the documents failed a security scan.')],
            ]);
        }

        $disk = config('media.private_disk');
        $path = $file->store('verifications/'.$verification->ulid, $disk);

        $verification->documents()->create([
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(BusinessVerification $verification): array
    {
        return [
            'ulid' => $verification->ulid,
            'status' => $verification->status,
            'legal_name' => $verification->legal_name,
            'decision_note' => $verification->decision_note,
            'submitted_at' => $verification->created_at?->toIso8601String(),
            'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
            'documents' => $verification->documents->map(fn ($d) => [
                'type' => $d->type,
                'original_name' => $d->original_name,
            ])->values(),
        ];
    }

    private function businessProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 422, 'Only a business profile can be verified.');

        return $profile;
    }
}
