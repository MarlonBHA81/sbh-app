<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Profile;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationParticipantController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    /**
     * Add participants to a group (owner/admin only).
     */
    public function store(Request $request, Conversation $conversation): ConversationResource
    {
        $viewer = $this->activeProfile($request);
        $conversation->load('participants.profile');

        $me = $conversation->participantFor($viewer);
        abort_unless($me !== null, 404);
        abort_unless($conversation->isGroup(), 403, 'Only group conversations accept new participants.');
        abort_unless($me->canManage(), 403);

        $data = $request->validate([
            'profile_ulids' => ['required', 'array', 'min:1', 'max:10'],
            'profile_ulids.*' => ['string', 'distinct'],
        ]);

        $this->messaging->addParticipants($conversation, $viewer, $data['profile_ulids']);

        $conversation->load(['participants.profile', 'lastMessage.profile']);
        $conversation->unread_count = 0;

        return new ConversationResource($conversation);
    }

    /**
     * Leave a conversation, or remove another participant (owner/admin).
     */
    public function destroy(Request $request, Conversation $conversation, Profile $profile): Response
    {
        $viewer = $this->activeProfile($request);
        $conversation->load('participants.profile');

        $me = $conversation->participantFor($viewer);
        abort_unless($me !== null, 404);

        // Leaving yourself.
        if ($profile->id === $viewer->id) {
            $this->messaging->leave($conversation, $me);

            return response()->noContent();
        }

        // Removing someone else: manager privileges required.
        abort_unless($me->canManage(), 403);

        $target = $conversation->participantFor($profile);
        abort_unless($target !== null, 404);

        // Admins may only remove plain members; the owner may remove anyone.
        if (! $me->isOwner() && $target->canManage()) {
            abort(403, 'Admins cannot remove owners or other admins.');
        }

        $this->messaging->removeParticipant($target);

        return response()->noContent();
    }

    /**
     * Promote/demote a participant (owner only).
     */
    public function updateRole(Request $request, Conversation $conversation, Profile $profile): ConversationResource
    {
        $viewer = $this->activeProfile($request);
        $conversation->load('participants.profile');

        $me = $conversation->participantFor($viewer);
        abort_unless($me !== null, 404);
        abort_unless($me->isOwner(), 403);

        $data = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $target = $conversation->participantFor($profile);
        abort_unless($target !== null, 404);
        abort_if($target->isOwner(), 403, 'The owner role cannot be changed here.');

        $target->update(['role' => $data['role']]);

        $conversation->load(['participants.profile', 'lastMessage.profile']);
        $conversation->unread_count = 0;

        return new ConversationResource($conversation);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
