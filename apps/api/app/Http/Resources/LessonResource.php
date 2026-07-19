<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Lesson $resource
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'title' => $this->title,
            'body' => $this->body,
            'external_url' => $this->external_url,
            'minutes' => $this->minutes,
            'journey_stage' => $this->journey_stage,
            'position' => $this->position,
            'track' => $this->whenLoaded('track', fn () => $this->track ? [
                'ulid' => $this->track->ulid,
                'title' => $this->track->title,
            ] : null),
            'is_completed' => (bool) ($this->is_completed ?? false),
        ];
    }
}
