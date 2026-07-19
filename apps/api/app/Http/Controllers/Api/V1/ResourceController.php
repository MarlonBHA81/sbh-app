<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LibraryResourceResource;
use App\Models\LibraryResource;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ResourceController extends Controller
{
    private const PER_PAGE = 20;

    /** Published resources — newest first, with optional filters and search. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', 'nullable', Rule::in(LibraryResource::TYPES)],
            'category' => ['sometimes', 'nullable', Rule::in(LibraryResource::CATEGORIES)],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $viewer = $this->activeProfile($request);

        $page = LibraryResource::query()
            ->visible()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['industry'] ?? null, fn ($q, $industry) => $q->where('industry', $industry))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($sub) => $sub->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        return LibraryResourceResource::collection($this->markSaved($page, $viewer));
    }

    /** The viewer's saved resources, most recently saved first. */
    public function saved(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $page = $viewer->savedResources()
            ->visible()
            ->orderByDesc('resource_saves.created_at')
            ->cursorPaginate(self::PER_PAGE);

        foreach ($page as $resource) {
            $resource->setAttribute('is_saved', true);
        }

        return LibraryResourceResource::collection($page);
    }

    public function show(Request $request, LibraryResource $resource): JsonResponse
    {
        abort_unless($resource->is_published, 404);

        $viewer = $this->activeProfile($request);
        $resource->setAttribute(
            'is_saved',
            $viewer->savedResources()->whereKey($resource->id)->exists(),
        );

        return response()->json(['data' => new LibraryResourceResource($resource)]);
    }

    public function save(Request $request, LibraryResource $resource): JsonResponse
    {
        abort_unless($resource->is_published, 404);

        $this->activeProfile($request)
            ->savedResources()
            ->syncWithoutDetaching([$resource->id]);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function unsave(Request $request, LibraryResource $resource): Response
    {
        $this->activeProfile($request)
            ->savedResources()
            ->detach($resource->id);

        return response()->noContent();
    }

    /** Flag which of the paginated resources the viewer has saved. */
    private function markSaved(CursorPaginator $page, Profile $viewer): CursorPaginator
    {
        $ids = collect($page->items())->pluck('id');

        $savedIds = $ids->isEmpty()
            ? collect()
            : $viewer->savedResources()->whereIn('resources.id', $ids)->pluck('resources.id');

        foreach ($page as $resource) {
            $resource->setAttribute('is_saved', $savedIds->contains($resource->id));
        }

        return $page;
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
