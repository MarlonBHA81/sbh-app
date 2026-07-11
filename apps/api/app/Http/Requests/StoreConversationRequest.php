<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([Conversation::KIND_DM, Conversation::KIND_GROUP])],

            // DM.
            'profile_ulid' => [Rule::requiredIf($this->input('kind') === Conversation::KIND_DM), 'string'],

            // Group.
            'title' => [Rule::requiredIf($this->input('kind') === Conversation::KIND_GROUP), 'nullable', 'string', 'max:80'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'member_ulids' => [Rule::requiredIf($this->input('kind') === Conversation::KIND_GROUP), 'array', 'min:1', 'max:49'],
            'member_ulids.*' => ['string', 'distinct'],
        ];
    }
}
