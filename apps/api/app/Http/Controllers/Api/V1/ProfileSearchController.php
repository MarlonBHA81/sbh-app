<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileSearchController extends Controller
{
    private const LIMIT = 8;

    public function __construct(private SafetyService $safety) {}

    /**
     * Lightweight prefix typeahead over handle OR name, for @autocomplete.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:30'],
        ]);

        $term = mb_strtolower(trim($validated['q']));

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');
        $excluded = $viewer ? $this->safety->blockedProfileIds($viewer) : [];

        $profiles = Profile::query()
            ->where(function ($query) use ($term) {
                $query->where('handle', 'like', $term.'%')
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term.'%']);
            })
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('id', $excluded))
            ->orderBy('handle')
            ->limit(self::LIMIT)
            ->get();

        return ProfileResource::collection($profiles);
    }
}
