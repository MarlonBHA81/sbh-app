<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\Profile;
use App\Support\MessagePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        return MessagePayload::present($this->resource, $viewer);
    }
}
