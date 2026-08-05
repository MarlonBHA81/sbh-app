<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduledAt;
use App\Models\Post;
use App\Services\Posts\PostTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    use ValidatesScheduledAt;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Post $post */
        $post = $this->route('post');
        $registry = app(PostTypeRegistry::class);
        $type = $post->type;

        $rules = [
            'type' => ['prohibited'],
            'parent_post_id' => ['prohibited'],
            'body' => ['sometimes', ...$registry->bodyRules($type)],
            'sensitive' => ['sometimes', 'boolean'],
        ];

        if ($payloadRules = $registry->payloadRules($type)) {
            $rules['payload'] = ['sometimes', 'array'];

            if ($this->has('payload')) {
                foreach ($payloadRules as $key => $keyRules) {
                    $rules["payload.{$key}"] = $keyRules;
                }
            }
        } else {
            $rules['payload'] = ['prohibited'];
        }

        if ($post->isPublished()) {
            // Published posts: only body/payload/sensitive are editable.
            return $rules + [
                'status' => ['prohibited'],
                'visibility' => ['prohibited'],
                'scheduled_at' => ['prohibited'],
                'media_ids' => ['prohibited'],
            ];
        }

        $rules['status'] = ['sometimes', Rule::in([Post::STATUS_DRAFT, Post::STATUS_SCHEDULED, Post::STATUS_PUBLISHED])];
        $rules['visibility'] = ['sometimes', Rule::in([Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS])];
        $rules['lat'] = ['nullable', 'numeric', 'between:-90,90'];
        $rules['lng'] = ['nullable', 'numeric', 'between:-180,180'];
        $rules['city'] = ['nullable', 'string', 'max:255'];
        $rules['country_code'] = ['nullable', 'string', 'size:2'];

        $becomingScheduled = $this->input('status') === Post::STATUS_SCHEDULED;

        $rules['scheduled_at'] = array_filter([
            $becomingScheduled && $post->scheduled_at === null ? 'required' : 'sometimes',
            'nullable',
            'date',
            $this->futureInUserTimezone(),
        ]);

        if ($max = $registry->maxMedia($type)) {
            $rules['media_ids'] = ['sometimes', 'array', 'min:'.$registry->minMedia($type), "max:{$max}"];
            $rules['media_ids.*'] = ['string', 'ulid', 'distinct'];
        } else {
            $rules['media_ids'] = ['prohibited'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Post $post */
        $post = $this->route('post');
        $registry = app(PostTypeRegistry::class);
        $check = $registry->payloadValidator($post->type);

        if (! $check || ! $this->has('payload')) {
            return;
        }

        $validator->after(function (Validator $validator) use ($check) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $check((array) $this->input('payload', []), function (string $key, string $message) use ($validator) {
                $validator->errors()->add($key, $message);
            });
        });
    }
}
