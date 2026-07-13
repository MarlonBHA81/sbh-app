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

        // WhatsApp accepts a phone number or a wa.me link; store the
        // canonical https://wa.me/<digits> form so validation below is a
        // plain URL check.
        $links = $this->input('social_links');

        if (is_array($links) && isset($links['whatsapp']) && is_string($links['whatsapp'])) {
            $raw = trim($links['whatsapp']);

            if ($raw !== '') {
                $digits = preg_replace('/\D+/', '', preg_replace('#^https?://(www\.)?wa\.me/#i', '', $raw)) ?? '';

                if (strlen($digits) >= 6 && strlen($digits) <= 15) {
                    $links['whatsapp'] = 'https://wa.me/'.$digits;
                    $this->merge(['social_links' => $links]);
                }
            }
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
            'social_links' => ['sometimes', 'nullable', 'array:linkedin,facebook,instagram,whatsapp'],
            'social_links.linkedin' => ['nullable', 'string', 'max:255', 'regex:#^https://(www\.)?linkedin\.com/.+#i'],
            'social_links.facebook' => ['nullable', 'string', 'max:255', 'regex:#^https://(www\.)?(facebook\.com|fb\.com)/.+#i'],
            'social_links.instagram' => ['nullable', 'string', 'max:255', 'regex:#^https://(www\.)?instagram\.com/.+#i'],
            'social_links.whatsapp' => ['nullable', 'string', 'max:255', 'regex:#^https://wa\.me/[0-9]{6,15}$#'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_private' => ['sometimes', 'boolean'],
            'dm_privacy' => ['sometimes', Rule::in(['everyone', 'followers', 'no_one'])],
        ];
    }

    public function messages(): array
    {
        return [
            'social_links.linkedin.regex' => 'Enter a full LinkedIn profile URL (https://linkedin.com/...).',
            'social_links.facebook.regex' => 'Enter a full Facebook page URL (https://facebook.com/...).',
            'social_links.instagram.regex' => 'Enter a full Instagram profile URL (https://instagram.com/...).',
            'social_links.whatsapp.regex' => 'Enter a WhatsApp number (e.g. +27 82 123 4567) or wa.me link.',
        ];
    }
}
