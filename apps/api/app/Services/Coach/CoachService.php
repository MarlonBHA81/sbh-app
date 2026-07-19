<?php

namespace App\Services\Coach;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\Profile;
use App\Services\Ai\AiGateway;
use App\Services\Ai\CannedCoachReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the AI Business Coach (V2 · LEARN): builds the member's context,
 * persists the conversation, and asks the AiGateway for a reply — always
 * falling back to a helpful canned reply so the coach works with no API key.
 */
class CoachService
{
    /** Recent turns sent as context to the model. */
    private const HISTORY_LIMIT = 20;

    public function __construct(private AiGateway $ai) {}

    public function conversationFor(Profile $profile): CoachConversation
    {
        return CoachConversation::query()->firstOrCreate(['profile_id' => $profile->id]);
    }

    /**
     * @return Collection<int, CoachMessage>
     */
    public function history(Profile $profile): Collection
    {
        return $this->conversationFor($profile)->messages()->get();
    }

    /**
     * Record the member's message, get a coach reply and persist it.
     *
     * @return array{user: CoachMessage, assistant: CoachMessage}
     */
    public function send(Profile $profile, string $body): array
    {
        $conversation = $this->conversationFor($profile);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($conversation, $profile, $body) {
            $userMessage = $conversation->messages()->create([
                'role' => CoachMessage::ROLE_USER,
                'body' => $body,
            ]);

            $reply = $this->ai->chat(
                $this->systemContext($profile),
                $this->gatewayMessages($conversation),
            ) ?? CannedCoachReply::generate($body);

            $assistantMessage = $conversation->messages()->create([
                'role' => CoachMessage::ROLE_ASSISTANT,
                'body' => $reply,
            ]);

            return [$userMessage, $assistantMessage];
        });

        return ['user' => $userMessage, 'assistant' => $assistantMessage];
    }

    public function reset(Profile $profile): void
    {
        $this->conversationFor($profile)->messages()->delete();
    }

    /**
     * The system prompt: who the coach is, plus the member's real context so
     * advice is relevant. Only fields that are actually set are included.
     */
    private function systemContext(Profile $profile): string
    {
        $facts = array_filter([
            $profile->name ? "Name: {$profile->name}" : null,
            $profile->category ? "Business / industry: {$profile->category}" : null,
            $profile->journey_stage ? 'Journey stage: '.str_replace('_', ' ', $profile->journey_stage) : null,
            ($profile->city ?: $profile->location) ? 'Location: '.($profile->city ?: $profile->location) : null,
        ]);

        $context = $facts === []
            ? 'The member has not shared profile details yet.'
            : "About the member:\n- ".implode("\n- ", $facts);

        return "You are the SBH Business Coach, a friendly and practical mentor for small "
            ."business owners (many in South Africa). Give concise, encouraging, actionable "
            ."advice in plain language. Prefer one or two concrete next steps over long theory. "
            ."Never invent statistics or make guarantees about results. If money or legal "
            ."specifics are involved, remind the member to verify locally.\n\n".$context;
    }

    /**
     * Map recent stored turns to the gateway's message shape.
     *
     * @return list<array{role: string, content: string}>
     */
    private function gatewayMessages(CoachConversation $conversation): array
    {
        return $conversation->messages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->sortBy('id')
            ->map(fn (CoachMessage $m) => ['role' => $m->role, 'content' => $m->body])
            ->values()
            ->all();
    }
}
