<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Profile;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageReactionController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function store(Request $request, Message $message): Response
    {
        $actor = $this->participant($request, $message);
        $emoji = $this->validatedEmoji($request);

        $this->messaging->addReaction($message, $actor, $emoji);

        return response()->noContent();
    }

    public function destroy(Request $request, Message $message): Response
    {
        $actor = $this->participant($request, $message);
        $emoji = $this->validatedEmoji($request);

        $this->messaging->removeReaction($message, $actor, $emoji);

        return response()->noContent();
    }

    private function validatedEmoji(Request $request): string
    {
        $data = $request->validate([
            'emoji' => [
                'required',
                'string',
                // Pragmatic single-emoji guard: at most 16 bytes.
                fn ($attribute, $value, $fail) => strlen((string) $value) <= 16
                    ? null
                    : $fail('The emoji is too long.'),
            ],
        ]);

        return $data['emoji'];
    }

    private function participant(Request $request, Message $message): Profile
    {
        /** @var Profile|null $profile */
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        abort_unless(
            $message->conversation->participantFor($profile) !== null,
            404,
        );

        return $profile;
    }
}
