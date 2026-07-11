<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduledAt;
use App\Models\Post;
use App\Services\Posts\PostTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    use ValidatesScheduledAt;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registry = app(PostTypeRegistry::class);

        $rules = [
            'type' => ['required', 'string', Rule::in($registry->types())],
            'visibility' => ['sometimes', Rule::in([Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS])],
            'status' => ['sometimes', Rule::in([Post::STATUS_DRAFT, Post::STATUS_SCHEDULED, Post::STATUS_PUBLISHED])],
            'scheduled_at' => [
                'required_if:status,'.Post::STATUS_SCHEDULED,
                'prohibited_unless:status,'.Post::STATUS_SCHEDULED,
                'nullable',
                'date',
                $this->futureInUserTimezone(),
            ],
            'sensitive' => ['sometimes', 'boolean'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ];

        $type = (string) $this->input('type');

        if (! $registry->has($type)) {
            // The 'type' in-rule will fail; skip type-specific rules.
            return $rules;
        }

        $rules['body'] = $registry->bodyRules($type);

        if ($payloadRules = $registry->payloadRules($type)) {
            $rules['payload'] = ['required', 'array'];

            foreach ($payloadRules as $key => $keyRules) {
                $rules["payload.{$key}"] = $keyRules;
            }
        } else {
            $rules['payload'] = ['prohibited'];
        }

        if ($max = $registry->maxMedia($type)) {
            $min = $registry->minMedia($type);
            $rules['media_ids'] = [
                $min > 0 ? 'required' : 'sometimes',
                'array',
                "min:{$min}",
                "max:{$max}",
            ];
            $rules['media_ids.*'] = ['string', 'ulid', 'distinct'];
        } else {
            $rules['media_ids'] = ['prohibited'];
        }

        $rules['parent_post_id'] = $registry->requiresParent($type)
            ? ['required', 'string', 'ulid']
            : ['prohibited'];

        return $rules;
    }
}
