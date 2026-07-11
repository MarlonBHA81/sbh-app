<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileSearchController extends Controller
{
    private const LIMIT = 8;

    /**
     * Lightweight prefix typeahead over handle OR name, for @autocomplete.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:30'],
        ]);

        $term = mb_strtolower(trim($validated['q']));

        $profiles = Profile::query()
            ->where(function ($query) use ($term) {
                $query->where('handle', 'like', $term.'%')
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term.'%']);
            })
            ->orderBy('handle')
            ->limit(self::LIMIT)
            ->get();

        return ProfileResource::collection($profiles);
    }
}
