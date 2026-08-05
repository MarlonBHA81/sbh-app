<?php

namespace App\Services\Posts\Handlers;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostTypeHandler;

class PollHandler implements PostTypeHandler
{
    public function eagerLoad(): array
    {
        return ['poll.options'];
    }

    public function createSatellite(Post $post, array $payload): void
    {
        $poll = $post->poll()->create([
            'question' => $post->body,
            'ends_at' => isset($payload['duration_hours'])
                ? now()->addHours((int) $payload['duration_hours'])
                : null,
        ]);

        foreach (array_values($payload['options']) as $position => $label) {
            $poll->options()->create([
                'label' => $label,
                'position' => $position,
            ]);
        }
    }

    public function updateSatellite(Post $post, array $payload): void
    {
        $post->poll?->delete();
        $post->load('poll.options');

        $this->createSatellite($post, $payload);

        $post->load('poll.options');
    }

    public function present(Post $post, ?Profile $viewer): array
    {
        $poll = $post->poll;

        if ($poll === null) {
            return [];
        }

        $total = $poll->votes_count;

        return [
            'poll' => [
                'question' => $poll->question,
                'ends_at' => $poll->ends_at?->toISOString(),
                'has_ended' => $poll->hasEnded(),
                'total_votes' => $total,
                'viewer_option_id' => $poll->viewerOptionId,
                'options' => $poll->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes_count' => $option->votes_count,
                    'percent' => $total > 0 ? round($option->votes_count / $total * 100, 1) : 0.0,
                ])->all(),
            ],
        ];
    }
}
