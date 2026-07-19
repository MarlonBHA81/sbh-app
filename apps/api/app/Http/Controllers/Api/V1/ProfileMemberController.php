<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Team management for multi-user business profiles (Roles P4). Members are
 * addressed by their personal-profile handle. Only owners/managers of the
 * business profile may manage its team; the owner cannot be changed or removed.
 */
class ProfileMemberController extends Controller
{
    /** List the team (owner + members). */
    public function index(Request $request, Profile $profile): JsonResponse
    {
        $this->guard($request, $profile);

        return response()->json(['data' => $this->team($profile)]);
    }

    /** Add a member by their personal handle. */
    public function store(Request $request, Profile $profile): JsonResponse
    {
        $this->guard($request, $profile);

        $data = $request->validate([
            'handle' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(ProfileMember::ASSIGNABLE_ROLES)],
        ]);

        $user = $this->resolveUser($data['handle']);
        abort_if($user->id === $profile->user_id, 422, 'The owner is already on the team.');

        ProfileMember::updateOrCreate(
            ['profile_id' => $profile->id, 'user_id' => $user->id],
            ['role' => $data['role']],
        );

        return response()->json(['data' => $this->team($profile)], 201);
    }

    /** Change a member's role (manager/poster). */
    public function updateRole(Request $request, Profile $profile, string $handle): JsonResponse
    {
        $this->guard($request, $profile);

        $data = $request->validate([
            'role' => ['required', Rule::in(ProfileMember::ASSIGNABLE_ROLES)],
        ]);

        $user = $this->resolveUser($handle);
        abort_if($user->id === $profile->user_id, 403, 'The owner role cannot be changed.');

        $member = $profile->memberships()->where('user_id', $user->id)->first();
        abort_unless($member !== null, 404);

        $member->update(['role' => $data['role']]);

        return response()->json(['data' => $this->team($profile)]);
    }

    /** Remove a member. */
    public function destroy(Request $request, Profile $profile, string $handle): Response
    {
        $this->guard($request, $profile);

        $user = $this->resolveUser($handle);
        abort_if($user->id === $profile->user_id, 403, 'The owner cannot be removed.');

        $profile->memberships()->where('user_id', $user->id)->delete();

        return response()->noContent();
    }

    /** Only owners/managers of a business profile may manage its team. */
    private function guard(Request $request, Profile $profile): void
    {
        abort_unless($profile->isBusiness(), 403, 'Only business profiles have a team.');
        abort_unless($profile->canManageMembers($request->user()), 403);
    }

    private function resolveUser(string $handle): User
    {
        $personal = Profile::query()
            ->where('handle', $handle)
            ->where('kind', Profile::KIND_PERSONAL)
            ->first();

        abort_unless($personal !== null, 422, 'No member with that handle.');

        return $personal->user;
    }

    /**
     * The owner (from user_id) plus pivot members, deduped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function team(Profile $profile): array
    {
        $entries = [$this->entry($profile->user, ProfileMember::ROLE_OWNER)];

        foreach ($profile->memberships()->with('user')->get() as $member) {
            if ($member->user_id === $profile->user_id) {
                continue; // backfilled owner row — already listed
            }
            $entries[] = $this->entry($member->user, $member->role);
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(User $user, string $role): array
    {
        $personal = $user->personalProfile;

        return [
            'handle' => $personal?->handle,
            'name' => $personal?->name ?? $user->name,
            'avatar_url' => $personal?->avatarUrl(),
            'role' => $role,
        ];
    }
}
