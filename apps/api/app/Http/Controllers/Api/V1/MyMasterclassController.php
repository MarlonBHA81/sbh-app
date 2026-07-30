<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Masterclass;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Facilitator self-serve masterclass authoring. Mirrors the "store owner" tier
 * for commerce: a facilitator (is_facilitator on the acting profile) creates
 * and manages their own cohorts; admins retain full control via Filament.
 */
class MyMasterclassController extends Controller
{
    /** The acting user's own masterclasses (as creator). */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeFacilitator($request);

        $classes = Masterclass::query()
            ->where('created_by', $request->user()->id)
            ->withCount('participants')
            ->orderByDesc('starts_at')
            ->get();

        return response()->json([
            'data' => $classes->map(fn (Masterclass $m) => $this->serialize($m)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('create', [Masterclass::class, $actor]);

        $data = $this->validated($request);

        $masterclass = Masterclass::create($data + [
            'created_by' => $request->user()->id,
            'facilitator_name' => $data['facilitator_name'] ?? $actor->name,
        ]);

        return response()->json([
            'data' => $this->serialize($masterclass->loadCount('participants')),
        ], 201);
    }

    public function update(Request $request, Masterclass $masterclass): JsonResponse
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('update', [$masterclass, $actor]);

        $masterclass->update($this->validated($request, partial: true));

        return response()->json([
            'data' => $this->serialize($masterclass->loadCount('participants')),
        ]);
    }

    public function destroy(Request $request, Masterclass $masterclass): Response
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('delete', [$masterclass, $actor]);

        $masterclass->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $rule = fn (array $rules) => $partial ? array_merge(['sometimes'], $rules) : $rules;

        return $request->validate([
            'title' => $rule(['required', 'string', 'max:160']),
            'description' => $rule(['required', 'string', 'max:5000']),
            'facilitator_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'starts_at' => $rule(['required', 'date']),
            'ends_at' => $rule(['required', 'date', 'after:starts_at']),
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Masterclass $masterclass): array
    {
        return [
            'ulid' => $masterclass->ulid,
            'title' => $masterclass->title,
            'description' => $masterclass->description,
            'facilitator_name' => $masterclass->facilitator_name,
            'starts_at' => $masterclass->starts_at->toIso8601String(),
            'ends_at' => $masterclass->ends_at->toIso8601String(),
            'capacity' => $masterclass->capacity,
            'participants_count' => (int) ($masterclass->participants_count ?? 0),
            'is_published' => (bool) $masterclass->is_published,
            'status' => $masterclass->hasEnded()
                ? 'ended'
                : ($masterclass->starts_at->isFuture() ? 'upcoming' : 'active'),
        ];
    }

    private function authorizeFacilitator(Request $request): void
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('create', [Masterclass::class, $actor]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
