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
            'kind' => ['required', Rule::in([AdEvent::KIND_IMPRESSION, AdEvent::KIND_CLICK])],
            'campaign_ulid' => ['required_without:slot_key', 'prohibits:slot_key', 'string'],
            'slot_key' => ['required_without:campaign_ulid', 'string'],
        ];
    }
}
