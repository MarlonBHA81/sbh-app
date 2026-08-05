<?php

namespace App\Http\Requests;

use App\Support\Handles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessProfileRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'sometimes',
                'nullable',
                'string',
                'regex:'.Handles::PATTERN,
                Rule::notIn(Handles::RESERVED),
                Rule::unique('profiles', 'handle'),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
