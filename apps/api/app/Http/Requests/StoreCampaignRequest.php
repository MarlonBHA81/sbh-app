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
            // Budgets are optional — campaigns are metrics-first and run for
            // their duration; a budget (when given) still caps impressions.
            'budget_cents' => [
                'sometimes',
                'nullable',
                'integer',
                'min:'.(int) config('ads.min_budget_cents'),
                'max:'.(int) config('ads.max_budget_cents'),
            ],
            'duration_days' => ['required', 'integer', 'min:1', 'max:'.(int) config('ads.max_duration_days')],
        ];
    }
}
