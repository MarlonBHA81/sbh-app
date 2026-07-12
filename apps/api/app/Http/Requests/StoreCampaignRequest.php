<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_ulid' => ['required', 'string'],
            'budget_cents' => [
                'required',
                'integer',
                'min:'.(int) config('ads.min_budget_cents'),
                'max:'.(int) config('ads.max_budget_cents'),
            ],
            'duration_days' => ['required', 'integer', 'min:1', 'max:'.(int) config('ads.max_duration_days')],
        ];
    }
}
