<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Profile;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    private const PER_PAGE = 30;

    public function __construct(private MessagingService $messaging) {}

    /**
     * Messages in a conversation, newest first. Bubbles from blocked pairs are
     * kept but masked; deleted messages are kept as tombstones.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        abort_unless($this->participates($conversation, $viewer), 404);

        $messages = $conversation->messages()
            ->withTrashed()
            ->with(['profile', 'media', 'reactions', 'replyTo.profile', 'conversation'])
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        return MessageResource::collection($messages);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $sender = $this->activeProfile($request);

        // Participant-only and must not have left the conversation.
        abort_unless($conversation->participantFor($sender) !== null, $this->participates($conversation, $sender) ? 403 : 404);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'media_ids' => ['nullable', 'array', 'max:4'],
            'media_ids.*' => ['string', 'distinct'],
            'reply_to_ulid' => ['nullable', 'string'],
        ]);

        $message = $this->messaging->sendMessage($conversation, $sender, $data);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Message $message): Response
    {
        $viewer = $this->activeProfile($request);

        abort_unless($message->profile_id === $viewer->id, 403);

        $this->messaging->deleteMessage($message);

        return response()->noContent();
    }

    /**
     * Whether the profile has ever been a participant (including having left).
     */
    private function participates(Conversation $conversation, Profile $profile): bool
    {
        return $conversation->participants()->where('profile_id', $profile->id)->exists();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
