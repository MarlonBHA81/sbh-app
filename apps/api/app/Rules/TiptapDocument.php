<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Tiptap/ProseMirror document (blog body) against a strict
 * whitelist of node and mark types. Anything outside the whitelist — a
 * <script>/<iframe> node, an unknown mark, a javascript: link — fails.
 */
class TiptapDocument implements ValidationRule
{
    /** Allowed node types and, where constrained, their permitted attributes. */
    private const NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList',
        'listItem', 'blockquote', 'codeBlock', 'image', 'hardBreak',
    ];

    private const MARKS = ['bold', 'italic', 'strike', 'code', 'link'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value['type'] ?? null) !== 'doc') {
            $fail('The :attribute must be a valid document.');

            return;
        }

        if (! $this->node($value, $fail)) {
            // $fail already invoked with a specific message.
        }
    }

    private function node(mixed $node, Closure $fail): bool
    {
        if (! is_array($node) || ! isset($node['type']) || ! is_string($node['type'])) {
            $fail('The :attribute contains an invalid node.');

            return false;
        }

        $type = $node['type'];

        if (! in_array($type, self::NODES, true)) {
            $fail("The :attribute contains an unsupported node type [{$type}].");

            return false;
        }

        if ($type === 'heading') {
            $level = $node['attrs']['level'] ?? null;

            if (! is_int($level) || $level < 1 || $level > 3) {
                $fail('The :attribute has an invalid heading level.');

                return false;
            }
        }

        if ($type === 'image' && ! $this->validImageSrc($node['attrs']['src'] ?? null)) {
            $fail('The :attribute has an image with a disallowed src.');

            return false;
        }

        if (isset($node['marks']) && ! $this->marks($node['marks'], $fail)) {
            return false;
        }

        foreach ($node['content'] ?? [] as $child) {
            if (! $this->node($child, $fail)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $marks
     */
    private function marks(mixed $marks, Closure $fail): bool
    {
        if (! is_array($marks)) {
            $fail('The :attribute contains invalid marks.');

            return false;
        }

        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($type) || ! in_array($type, self::MARKS, true)) {
                $fail('The :attribute contains an unsupported mark.');

                return false;
            }

            if ($type === 'link' && ! $this->validHref($mark['attrs']['href'] ?? null)) {
                $fail('The :attribute contains a link with a disallowed scheme.');

                return false;
            }
        }

        return true;
    }

    private function validImageSrc(mixed $src): bool
    {
        if (! is_string($src) || $src === '') {
            return false;
        }

        // Relative paths are ours; absolute URLs must be http(s).
        if (! preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $src)) {
            return true;
        }

        return $this->validHref($src);
    }

    private function validHref(mixed $href): bool
    {
        return is_string($href)
            && preg_match('#^https?://#i', $href) === 1;
    }
}
