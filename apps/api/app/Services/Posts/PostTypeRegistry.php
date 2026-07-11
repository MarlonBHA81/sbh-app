<?php

namespace App\Services\Posts;

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
    }
}
