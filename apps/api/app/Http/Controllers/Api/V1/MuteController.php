<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Mute;
use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MuteController extends Controller
{
    public function __construct(private SafetyService $safety) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $this->activeProfile($request);

        $muted = Profile::query()
            ->whereIn('id', Mute::query()
                ->where('muter_profile_id', $actor->id)
                ->select('muted_profile_id'))
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return ProfileResource::collection($muted);
    }

    public function store(Request $request, string $handle): JsonResponse
    {
        $actor = $this->activeProfile($request);
        $target = $this->resolveByHandle($handle);

        $this->safety->mute($actor, $target);

        return response()->json(['status' => 'muted'], 201);
    }

    public function destroy(Request $request, string $handle): Response
    {
        $actor = $this->activeProfile($request);
        $target = $this->resolveByHandle($handle);

        $this->safety->unmute($actor, $target);

        return response()->noContent();
    }

    private function resolveByHandle(string $handle): Profile
    {
        return Profile::query()->where('handle', mb_strtolower($handle))->firstOrFail();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
