<?php

namespace App\Http\Requests;

use App\Models\AdEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([AdEvent::KIND_IMPRESSION, AdEvent::KIND_CLICK, AdEvent::KIND_LINK_CLICK])],
            'campaign_ulid' => ['required_without_all:slot_key,opportunity_ulid,room_ulid', 'prohibits:slot_key,opportunity_ulid,room_ulid', 'string'],
            'slot_key' => ['required_without_all:campaign_ulid,opportunity_ulid,room_ulid', 'prohibits:opportunity_ulid,room_ulid', 'string'],
            'opportunity_ulid' => ['required_without_all:campaign_ulid,slot_key,room_ulid', 'prohibits:room_ulid', 'string'],
            'room_ulid' => ['required_without_all:campaign_ulid,slot_key,opportunity_ulid', 'string'],
        ];
    }
}
