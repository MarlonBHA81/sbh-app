<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resolves feature flags from the config/features.php registry, overlaid with
 * any super-admin Setting override ('features.<key>'). This is the single
 * source of truth for both the server gates (Features::enabled) and the map
 * surfaced to the web client (Features::all via /api/v1/me).
 */
class Features
{
    /** Setting key prefix for a flag override. */
    private const PREFIX = 'features.';

    /**
     * The resolved on/off state of every registered flag.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $flags = [];

        foreach (self::registry() as $key => $meta) {
            $flags[$key] = self::resolve($key, (bool) ($meta['default'] ?? true));
        }

        return $flags;
    }

    /** Whether a single feature is on (defaults to its registry default). */
    public static function enabled(string $key): bool
    {
        $default = (bool) (self::registry()[$key]['default'] ?? true);

        return self::resolve($key, $default);
    }

    /**
     * The flag registry (label/description/default/group per key).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registry(): array
    {
        return config('features.flags', []);
    }

    private static function resolve(string $key, bool $default): bool
    {
        return (bool) Setting::get(self::PREFIX.$key, $default);
    }
}
