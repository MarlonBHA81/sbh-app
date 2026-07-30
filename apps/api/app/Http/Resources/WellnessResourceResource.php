<?php

namespace App\Http\Resources;

use App\Models\WellnessResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WellnessResource
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
