<?php

namespace App\Models;

use App\Casts\EncryptedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        // Encrypted at rest: settings hold live integration credentials. The
        // cast tolerates existing plaintext rows and re-encrypts them on write.
        return [
            'value' => EncryptedJson::class,
        ];
    }

    private const CACHE_PREFIX = 'setting:';

    /**
     * Fetch a setting value (cached forever until changed).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            $setting = static::query()->where('key', $key)->first();

            // Wrap in an array so a genuine null value is distinguishable
            // from a missing row when cached.
            return $setting ? ['value' => $setting->value] : null;
        });

        return $value === null ? $default : $value['value'];
    }

    /**
     * Persist a setting value and refresh its cache.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
        Cache::rememberForever(self::CACHE_PREFIX.$key, fn () => ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
