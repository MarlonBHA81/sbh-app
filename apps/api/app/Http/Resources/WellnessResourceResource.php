<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WellnessResource
 */
class WellnessResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
