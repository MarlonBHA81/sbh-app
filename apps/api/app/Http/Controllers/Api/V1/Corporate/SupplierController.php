<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corporate self-serve: search verified suppliers to invite into a cohort.
 * Suppliers are a shared pool (any verified business), so this is not scoped to
 * the corporate — but the caller must still be running a corporate profile.
 */
class SupplierController extends Controller
{
    use InteractsWithActiveCorporate;

    public function index(Request $request): JsonResponse
    {
        $this->activeCorporate($request);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim($validated['q'] ?? '');

        $suppliers = Profile::query()
            ->business()
            ->where('is_verified', true)
            ->when($term !== '', function (Builder $query) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('handle', 'like', $like));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['ulid', 'name', 'handle']);

        return response()->json([
            'data' => $suppliers->map(fn (Profile $p) => [
                'ulid' => $p->ulid,
                'name' => $p->name,
                'handle' => $p->handle,
            ])->values(),
        ]);
    }
}
