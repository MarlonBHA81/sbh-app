<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduledAt;
use App\Models\Post;
use App\Models\Topic;
use App\Services\Posts\PostService;
use App\Services\Posts\PostTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'topic_ids' => ['sometimes', 'array', 'max:'.PostService::MAX_TOPICS],
            'topic_ids.*' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = is_numeric($value)
                        ? Topic::query()->whereKey((int) $value)->exists()
                        : (is_string($value) && Topic::query()->where('slug', $value)->exists());

                    if (! $exists) {
                        $fail('The selected topic does not exist.');
                    }
                },
            ],
        ];

        $type = (string) $this->input('type');

        if (! $registry->has($type)) {
            // The 'type' in-rule will fail; skip type-specific rules.
            return $rules;
        }

        $rules['body'] = $registry->bodyRules($type);

        if ($payloadRules = $registry->payloadRules($type)) {
            // The payload object is only required when at least one of its keys
            // is itself required (e.g. audio's optional title => payload optional).
            $payloadRequired = collect($payloadRules)
                ->contains(fn ($keyRules) => in_array('required', (array) $keyRules, true));

            $rules['payload'] = [$payloadRequired ? 'required' : 'sometimes', 'array'];

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

    public function withValidator(Validator $validator): void
    {
        $registry = app(PostTypeRegistry::class);
        $type = (string) $this->input('type');

        if (! $registry->has($type) || ! ($check = $registry->payloadValidator($type))) {
            return;
        }

        $validator->after(function (Validator $validator) use ($check) {
            if ($validator->errors()->isNotEmpty()) {
                return; // shape already invalid; skip cross-field checks
            }

            $check((array) $this->input('payload', []), function (string $key, string $message) use ($validator) {
                $validator->errors()->add($key, $message);
            });
        });
    }
}
