<?php

namespace App\Services\Posts\Handlers;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostTypeHandler;
use Illuminate\Support\Carbon;

class EventHandler implements PostTypeHandler
{
    public function eagerLoad(): array
    {
        return ['event'];
    }

    public function createSatellite(Post $post, array $payload): void
    {
        $post->event()->create([
            'title' => $payload['title'],
            'starts_at' => Carbon::parse($payload['starts_at']),
            'ends_at' => isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : null,
            'venue' => $payload['venue'] ?? null,
        ]);
    }

    public function updateSatellite(Post $post, array $payload): void
    {
        $event = $post->event;

        if ($event === null) {
            $this->createSatellite($post, $payload);
            $post->load('event');

            return;
        }

        $event->update([
            'title' => $payload['title'],
            'starts_at' => Carbon::parse($payload['starts_at']),
            'ends_at' => isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : null,
            'venue' => $payload['venue'] ?? null,
        ]);

        $post->load('event');
    }

    public function present(Post $post, ?Profile $viewer): array
    {
        $event = $post->event;

        if ($event === null) {
            return [];
        }

        return [
            'event' => [
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toISOString(),
                'ends_at' => $event->ends_at?->toISOString(),
                'venue' => $event->venue,
                'going_count' => $event->going_count,
                'interested_count' => $event->interested_count,
                'viewer_rsvp' => $event->viewerRsvp,
            ],
        ];
    }
}
