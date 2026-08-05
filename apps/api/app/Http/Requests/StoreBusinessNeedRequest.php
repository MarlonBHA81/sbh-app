<?php

namespace App\Http\Requests;

use App\Models\BusinessNeed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessNeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([BusinessNeed::KIND_OFFERING, BusinessNeed::KIND_SEEKING])],
            'business_category_id' => ['required', 'integer', Rule::exists('business_categories', 'id')],
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
