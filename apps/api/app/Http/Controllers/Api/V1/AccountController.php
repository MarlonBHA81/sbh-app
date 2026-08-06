<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountDataService;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Data-subject rights (GDPR Art. 15/17, POPIA §23/§24): a user can export a
 * copy of their personal data and permanently delete their account.
 */
class AccountController extends Controller
{
    public function __construct(private AccountDataService $data) {}

    /** Right of access: a machine-readable export of the user's own data. */
    public function export(Request $request): JsonResponse
    {
        Activity::log('account.exported', actor: $request->user());

        return response()->json([
            'data' => $this->data->export($request->user()),
        ]);
    }

    /** Right to erasure: permanently delete the account and its content. */
    public function destroy(Request $request): Response
    {
        $user = $request->user();

        // A password-holding account must confirm; social-only accounts
        // (no password) confirm by typing their handle instead.
        if ($user->password !== null) {
            $request->validate(['password' => ['required', 'string']]);

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['That password is incorrect.'],
                ]);
            }
        } else {
            $request->validate(['confirm_handle' => ['required', 'string']]);
            $handle = $user->personalProfile?->handle;

            if ($handle === null || $request->input('confirm_handle') !== $handle) {
                throw ValidationException::withMessages([
                    'confirm_handle' => ['Type your @handle exactly to confirm deletion.'],
                ]);
            }
        }

        // Log before deletion. The activity_logs.user_id FK is null-on-delete,
        // so also stamp the id/handle into meta to preserve who erased the account.
        Activity::log('account.deleted', actor: $user, meta: [
            'user_id' => $user->id,
            'handle' => $user->personalProfile?->handle,
        ]);

        $this->data->deleteAccount($user);

        return response()->noContent();
    }
}
