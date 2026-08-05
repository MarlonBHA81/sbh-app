<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'in:en,bn,ar,es,fr'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
