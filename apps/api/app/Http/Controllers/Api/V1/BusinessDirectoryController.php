<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Business\DirectoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessDirectoryController extends Controller
{
    public function index(Request $request, DirectoryService $directory): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $viewer = $this->activeProfile($request);

        $results = $directory->search($viewer, [
            'category' => $validated['category'] ?? null,
            'q' => $validated['q'] ?? null,
            'country' => $validated['country'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        return ProfileResource::collection($results);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
