<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Profile;
use App\Services\Gamification\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChallengeController extends Controller
{
    public function __construct(private ChallengeService $challenges) {}

    /** Active + upcoming challenges first, most recently ended after. */
    public function index(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $challenges = Challenge::query()
            ->visible()
            ->withCount('participants')
            ->orderByRaw('ends_at >= ? desc', [now()])
            ->orderBy('ends_at')
            ->limit(20)
            ->get();

        $joinedIds = $viewer->challenges()->pluck('challenges.id')->all();

        return response()->json([
            'data' => $challenges->map(
                fn (Challenge $challenge) => $this->serialize($challenge, in_array($challenge->id, $joinedIds, true)),
            ),
        ]);
    }

    public function show(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->is_active, 404);

        $viewer = $this->activeProfile($request);
        $challenge->loadCount('participants');

        $entries = collect($this->challenges->leaderboard($challenge))
            ->map(fn (array $row) => [
                'position' => $row['rank'],
                'xp' => $row['points'],
                'profile' => $this->liteProfile($row['profile']),
            ])
            ->values();

        $me = $this->challenges->viewerRow($challenge, $viewer);

        return response()->json([
            'data' => $this->serialize($challenge, $me !== null) + [
                'entries' => $entries,
                'me' => $me === null ? null : [
                    'position' => $me['rank'],
                    'xp' => $me['points'],
                ],
            ],
        ]);
    }

    /** Create a challenge (facilitators + admins — Roles P2). */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('create', [Challenge::class, $actor]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $challenge = Challenge::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_active' => true,
            'created_by_profile_id' => $actor->id,
        ]);

        return response()->json([
            'data' => $this->serialize($challenge->loadCount('participants'), false),
        ], 201);
    }

    /** Update a challenge the actor created (or any, for admins — Roles P2). */
    public function update(Request $request, Challenge $challenge): JsonResponse
    {
        $actor = $this->activeProfile($request);
        Gate::authorize('update', [$challenge, $actor]);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $challenge->update($data);

        $joined = $challenge->participants()->where('profile_id', $actor->id)->exists();

        return response()->json([
            'data' => $this->serialize($challenge->loadCount('participants'), $joined),
        ]);
    }

    public function join(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->is_active, 404);

        $this->challenges->join($challenge, $this->activeProfile($request));

        return response()->json(['data' => ['joined' => true]], 201);
    }

    public function leave(Request $request, Challenge $challenge): Response
    {
        $this->challenges->leave($challenge, $this->activeProfile($request));

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Challenge $challenge, bool $joined): array
    {
        return [
            'ulid' => $challenge->ulid,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'starts_at' => $challenge->starts_at->toISOString(),
            'ends_at' => $challenge->ends_at->toISOString(),
            'status' => $challenge->hasEnded()
                ? 'ended'
                : ($challenge->starts_at->isFuture() ? 'upcoming' : 'active'),
            'participants_count' => (int) ($challenge->participants_count ?? 0),
            'joined' => $joined,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function liteProfile(Profile $profile): array
    {
        return [
            'ulid' => $profile->ulid,
            'handle' => $profile->handle,
            'name' => $profile->name,
            'avatar_url' => $profile->avatarUrl(),
            'xp_total' => (int) $profile->xp_total,
        ];
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
