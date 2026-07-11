<?php

namespace App\Http\Requests\Auth;

use App\Support\Handles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default()],
            'handle' => [
                'sometimes',
                'nullable',
                'string',
                'regex:'.Handles::PATTERN,
                Rule::notIn(Handles::RESERVED),
                Rule::unique('profiles', 'handle'),
            ],
        ];
    }
}
