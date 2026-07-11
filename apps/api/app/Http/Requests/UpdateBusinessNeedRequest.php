<?php

namespace App\Http\Requests;

use App\Models\BusinessNeed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessNeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', Rule::in([BusinessNeed::KIND_OFFERING, BusinessNeed::KIND_SEEKING])],
            'business_category_id' => ['sometimes', 'integer', Rule::exists('business_categories', 'id')],
            'description' => ['sometimes', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
