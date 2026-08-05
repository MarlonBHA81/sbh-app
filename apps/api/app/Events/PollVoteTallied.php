<?php

namespace App\Events;

use App\Models\Poll;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Public post channel event: a poll's tallies changed after a vote.
 */
class PollVoteTallied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Poll $poll) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('post.'.$this->poll->post->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'PollVoteTallied';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $poll = $this->poll->loadMissing('options');

        return [
            'post_ulid' => $poll->post->ulid,
            'votes_count' => (int) $poll->votes_count,
            'options' => $poll->options->map(fn ($option) => [
                'id' => $option->id,
                'votes_count' => (int) $option->votes_count,
            ])->all(),
        ];
    }
}
