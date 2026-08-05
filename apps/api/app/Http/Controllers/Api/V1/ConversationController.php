<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Profile;
use App\Services\MessagingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ConversationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private MessagingService $messaging) {}

    /**
     * The active profile's conversations, most-recently-active first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('profile_id', $viewer->id)->whereNull('left_at'))
            ->with(['participants.profile', 'lastMessage.profile'])
            ->orderByRaw('last_message_at is null')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        $this->hydrateUnread($conversations->getCollection(), $viewer);

        return ConversationResource::collection($conversations);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $creator = $this->activeProfile($request);
        $data = $request->validated();

        if ($data['kind'] === Conversation::KIND_DM) {
            $target = Profile::query()->where('ulid', $data['profile_ulid'])->first();
            abort_unless($target, 404);

            $conversation = $this->messaging->findOrCreateDm($creator, $target);
        } else {
            $conversation = $this->messaging->createGroup(
                $creator,
                $data['title'],
                $data['member_ulids'],
                $data['rules'] ?? null,
            );
        }

        $conversation->load(['participants.profile', 'lastMessage.profile']);
        $this->hydrateUnread(new Collection([$conversation]), $creator);

        return (new ConversationResource($conversation))->response()->setStatusCode(201);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $viewer = $this->activeProfile($request);
        $conversation->load(['participants.profile', 'lastMessage.profile']);

        abort_unless($conversation->hasActiveParticipant($viewer), 404);

        $this->hydrateUnread(new Collection([$conversation]), $viewer);

        return new ConversationResource($conversation);
    }

    /**
     * Rename a group / update its rules (owner or admin only).
     */
    public function update(Request $request, Conversation $conversation): ConversationResource
    {
        $viewer = $this->activeProfile($request);
        $conversation->load('participants.profile');

        $participant = $conversation->participantFor($viewer);
        abort_unless($participant !== null, 404);
        abort_unless($conversation->isGroup(), 403, 'Only group conversations can be edited.');
        abort_unless($participant->canManage(), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:80'],
            'rules' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $conversation->fill($data)->save();
        $this->hydrateUnread(new Collection([$conversation]), $viewer);

        return new ConversationResource($conversation);
    }

    /**
     * Advance the caller's read cursor within a conversation.
     */
    public function read(Request $request, Conversation $conversation): Response
    {
        $viewer = $this->activeProfile($request);
        $conversation->load('participants.profile');

        $participant = $conversation->participantFor($viewer);
        abort_unless($participant !== null, 404);

        $data = $request->validate(['message_ulid' => ['required', 'string']]);

        $message = Message::query()->where('ulid', $data['message_ulid'])->first();
        abort_unless($message !== null, 404);

        $this->messaging->markRead($conversation, $participant, $message);

        return response()->noContent();
    }

    /**
     * Total unread across every conversation the caller participates in.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $participations = ConversationParticipant::query()
            ->where('profile_id', $viewer->id)
            ->whereNull('left_at')
            ->get(['conversation_id', 'last_read_message_id']);

        $count = $participations->sum(fn (ConversationParticipant $p) => Message::query()
            ->where('conversation_id', $p->conversation_id)
            ->where('profile_id', '!=', $viewer->id)
            ->where('id', '>', $p->last_read_message_id ?? 0)
            ->count());

        return response()->json(['count' => (int) $count]);
    }

    /**
     * Attach a transient unread_count attribute to each conversation.
     *
     * @param  Collection<int, Conversation>  $conversations
     */
    private function hydrateUnread(Collection $conversations, Profile $viewer): void
    {
        $conversations->each(function (Conversation $conversation) use ($viewer) {
            $participant = $conversation->participants
                ->firstWhere(fn (ConversationParticipant $p) => $p->profile_id === $viewer->id && $p->left_at === null);

            if ($participant === null) {
                $conversation->unread_count = 0;

                return;
            }

            $conversation->unread_count = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('profile_id', '!=', $viewer->id)
                ->where('id', '>', $participant->last_read_message_id ?? 0)
                ->count();
        });
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
