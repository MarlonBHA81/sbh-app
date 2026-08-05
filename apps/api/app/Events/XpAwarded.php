<?php

namespace App\Events;

use App\Models\Profile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ephemeral toast payload broadcast to a profile's private channel when XP is
 * awarded. Carries no persistent DB row — purely a realtime signal.
 */
class XpAwarded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Profile $profile,
        public int $points,
        public string $actionKey,
        public int $xpTotal,
        public string $label,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('profile.'.$this->profile->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'XpAwarded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'points' => $this->points,
            'action_key' => $this->actionKey,
            'xp_total' => $this->xpTotal,
            'label' => $this->label,
        ];
    }
}
