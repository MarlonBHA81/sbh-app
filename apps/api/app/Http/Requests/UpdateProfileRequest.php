<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Support\Handles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->handle)) {
            $this->merge(['handle' => mb_strtolower($this->handle)]);
        }
    }

    public function rules(): array
    {
        $profile = $this->route('profile');

        // A business category may only be attached to a business profile.
        $isBusiness = $profile instanceof Profile && $profile->isBusiness();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'business_category_id' => [
                'sometimes',
                'nullable',
                Rule::prohibitedIf(! $isBusiness),
                'integer',
                Rule::exists('business_categories', 'id'),
            ],
            'handle' => [
                'sometimes',
                'string',
                'regex:'.Handles::PATTERN,
                Rule::notIn(Handles::RESERVED),
                Rule::unique('profiles', 'handle')->ignore($profile?->id),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_private' => ['sometimes', 'boolean'],
            'dm_privacy' => ['sometimes', Rule::in(['everyone', 'followers', 'no_one'])],
        ];
    }
}
