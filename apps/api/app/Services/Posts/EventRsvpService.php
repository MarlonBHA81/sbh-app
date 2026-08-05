<?php

namespace App\Services\Posts;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class EventRsvpService
{
    /**
     * Set the viewer's RSVP status for an event and return the fresh event.
     */
    public function rsvp(Profile $profile, Post $post, string $status): Event
    {
        /** @var Event|null $event */
        $event = $post->event()->first();

        abort_unless($event !== null, 404, 'This post is not an event.');

        DB::transaction(function () use ($event, $profile, $status) {
            $existing = EventRsvp::query()
                ->where('event_id', $event->id)
                ->where('profile_id', $profile->id)
                ->lockForUpdate()
                ->first();

            $current = $existing?->status;

            if ($current === $status || ($current === null && $status === Event::RSVP_NONE)) {
                return; // no change
            }

            if ($current !== null) {
                Event::query()->whereKey($event->id)
                    ->where($this->column($current), '>', 0)
                    ->decrement($this->column($current));
            }

            if ($status === Event::RSVP_NONE) {
                $existing?->delete();

                return;
            }

            if ($existing) {
                $existing->update(['status' => $status]);
            } else {
                EventRsvp::create([
                    'event_id' => $event->id,
                    'profile_id' => $profile->id,
                    'status' => $status,
                ]);
            }

            $event->increment($this->column($status));
        });

        $event = $event->fresh();
        $event->viewerRsvp = $status === Event::RSVP_NONE ? null : $status;

        return $event;
    }

    private function column(string $status): string
    {
        return $status === Event::RSVP_GOING ? 'going_count' : 'interested_count';
    }
}
