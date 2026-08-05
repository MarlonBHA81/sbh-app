<?php

namespace App\Services\Posts;

use App\Rules\TiptapDocument;
use InvalidArgumentException;

/**
 * Registry of the post types supported by the platform.
 *
 * Each entry declares the validation rules for the type's payload/body plus
 * behavioural flags. Later milestones add new types (video, audio, blog,
 * poll, quiz, event, job, portfolio, ...) by calling register() with a new
 * definition — no controller/service/resource changes are required.
 */
class PostTypeRegistry
{
    /**
     * Defaults merged into every registered definition.
     *
     * - payload_rules: validation rules keyed by payload key (empty array
     *   means the type accepts no payload at all).
     * - body_rules: validation rules for the free-text body/caption.
     * - min_media / max_media: number of attached media items allowed
     *   (0/0 means media attachments are prohibited).
     * - requires_parent: whether a parent_post_id reference is required.
     * - hidden_payload: payload is hidden from list/show responses and only
     *   returned via the reveal endpoint.
     */
    private const DEFAULTS = [
        'payload_rules' => [],
        'body_rules' => ['nullable', 'string', 'max:5000'],
        'min_media' => 0,
        'max_media' => 0,
        'requires_parent' => false,
        'hidden_payload' => false,
        // Constrain attached media to a single Media::TYPE_* (null = any).
        'media_type' => null,
        // FQCN of a PostTypeHandler owning satellite rows (null = none).
        'handler' => null,
        // Optional closure(array $payload, Closure $fail): void for cross-field
        // payload checks that plain rules can't express.
        'payload_validator' => null,
    ];

    /** @var array<string, array<string, mixed>> */
    private array $types = [];

    public function __construct()
    {
        $this->registerDefaultTypes();
    }

    /**
     * Register (or override) a post type definition.
     */
    public function register(string $type, array $definition = []): void
    {
        $this->types[$type] = array_merge(self::DEFAULTS, $definition);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->types);
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $type): array
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Unknown post type [{$type}].");
        }

        return $this->types[$type];
    }

    /**
     * Validation rules for the payload, keyed by payload key.
     *
     * @return array<string, array<int, mixed>>
     */
    public function payloadRules(string $type): array
    {
        return $this->get($type)['payload_rules'];
    }

    /**
     * @return array<int, mixed>
     */
    public function bodyRules(string $type): array
    {
        return $this->get($type)['body_rules'];
    }

    public function minMedia(string $type): int
    {
        return $this->get($type)['min_media'];
    }

    public function maxMedia(string $type): int
    {
        return $this->get($type)['max_media'];
    }

    public function requiresMedia(string $type): bool
    {
        return $this->minMedia($type) > 0;
    }

    public function requiresParent(string $type): bool
    {
        return $this->get($type)['requires_parent'];
    }

    public function hiddenPayload(string $type): bool
    {
        return $this->has($type) && $this->get($type)['hidden_payload'];
    }

    public function mediaType(string $type): ?string
    {
        return $this->get($type)['media_type'];
    }

    /**
     * Resolve the satellite handler for a type, or null if it has none.
     */
    public function handler(string $type): ?PostTypeHandler
    {
        $class = $this->has($type) ? $this->get($type)['handler'] : null;

        return $class === null ? null : app($class);
    }

    /**
     * @return callable|null
     */
    public function payloadValidator(string $type)
    {
        return $this->get($type)['payload_validator'];
    }

    /**
     * Every satellite eager-load path across all registered types. Included in
     * PostService::EAGER so mixed feeds resolve satellites without N+1 queries.
     *
     * @return list<string>
     */
    public function satelliteEagerLoads(): array
    {
        $paths = [];

        foreach ($this->types() as $type) {
            if ($handler = $this->handler($type)) {
                $paths = array_merge($paths, $handler->eagerLoad());
            }
        }

        return array_values(array_unique($paths));
    }

    private function registerDefaultTypes(): void
    {
        $this->register('text', [
            'body_rules' => ['required', 'string', 'max:5000'],
        ]);

        $this->register('link', [
            'payload_rules' => [
                'url' => ['required', 'string', 'url', 'max:2048'],
                'title' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
            ],
        ]);

        $this->register('image', [
            'min_media' => 1,
            'max_media' => 4,
        ]);

        $this->register('quote', [
            'body_rules' => ['required', 'string', 'max:5000'],
            'requires_parent' => true,
        ]);

        $this->register('repost', [
            'body_rules' => ['prohibited'],
            'requires_parent' => true,
        ]);

        $this->register('typewriter', [
            'payload_rules' => [
                'text' => ['required', 'string', 'max:5000'],
                'speed' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ],
        ]);

        $this->register('magnifier', [
            'payload_rules' => [
                'text' => ['required', 'string', 'max:5000'],
                'image_media_id' => ['nullable', 'string', 'ulid'],
            ],
        ]);

        $this->register('secret', [
            'payload_rules' => [
                'secret_text' => ['required', 'string', 'max:5000'],
            ],
            'hidden_payload' => true,
        ]);

        $this->register('checkin', [
            'payload_rules' => [
                'place_name' => ['required', 'string', 'max:255'],
            ],
        ]);

        $this->registerMilestone5Types();
    }

    private function registerMilestone5Types(): void
    {
        $this->register('video', [
            'body_rules' => ['nullable', 'string', 'max:5000'], // caption
            'min_media' => 1,
            'max_media' => 1,
            'media_type' => 'video',
        ]);

        $this->register('audio', [
            'payload_rules' => [
                'title' => ['nullable', 'string', 'max:120'],
            ],
            'min_media' => 1,
            'max_media' => 1,
            'media_type' => 'audio',
        ]);

        $this->register('blog', [
            'payload_rules' => [
                'title' => ['required', 'string', 'max:120'],
                'doc' => ['required', 'array', new TiptapDocument],
                'excerpt' => ['nullable', 'string', 'max:300'],
            ],
        ]);

        $this->register('poll', [
            'body_rules' => ['nullable', 'string', 'max:5000'], // question text
            'payload_rules' => [
                'options' => ['required', 'array', 'min:2', 'max:6'],
                'options.*' => ['required', 'string', 'max:80'],
                'duration_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            ],
            'handler' => Handlers\PollHandler::class,
        ]);

        $this->register('quiz', [
            'payload_rules' => [
                'questions' => ['required', 'array', 'min:1', 'max:10'],
                'questions.*.question' => ['required', 'string', 'max:255'],
                'questions.*.options' => ['required', 'array', 'min:2', 'max:4'],
                'questions.*.options.*' => ['required', 'string', 'max:255'],
                'questions.*.correct_index' => ['required', 'integer', 'min:0', 'max:3'],
            ],
            'handler' => Handlers\QuizHandler::class,
            'payload_validator' => function (array $payload, \Closure $fail): void {
                foreach ($payload['questions'] ?? [] as $i => $question) {
                    $count = is_array($question['options'] ?? null) ? count($question['options']) : 0;
                    $index = $question['correct_index'] ?? null;

                    if (is_int($index) && $index >= $count) {
                        $fail("payload.questions.{$i}.correct_index", 'The correct answer must reference one of the options.');
                    }
                }
            },
        ]);

        $this->register('event', [
            'payload_rules' => [
                'title' => ['required', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
                'venue' => ['nullable', 'string', 'max:255'],
            ],
            'max_media' => 1,
            'media_type' => 'image',
            'handler' => Handlers\EventHandler::class,
        ]);

        $this->register('job', [
            'payload_rules' => [
                'title' => ['required', 'string', 'max:255'],
                'company' => ['nullable', 'string', 'max:255'],
                'location' => ['nullable', 'string', 'max:255'],
                'employment_type' => ['required', 'string', 'in:full_time,part_time,contract,freelance,internship'],
                'salary_min' => ['nullable', 'integer', 'min:0'],
                'salary_max' => ['nullable', 'integer', 'min:0', 'gte:payload.salary_min'],
                'currency' => ['nullable', 'string', 'size:3'],
                'apply_url' => ['nullable', 'url', 'max:2048'],
                'expires_at' => ['nullable', 'date'],
            ],
            'handler' => Handlers\JobHandler::class,
        ]);

        $this->register('portfolio', [
            'payload_rules' => [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
            ],
            'min_media' => 1,
            'max_media' => 10,
            'media_type' => 'image',
        ]);
    }
}
