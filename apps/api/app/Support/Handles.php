<?php

namespace App\Support;

use App\Models\Profile;
use Illuminate\Support\Str;

class Handles
{
    public const PATTERN = '/^[a-z0-9_]{3,30}$/';

    public const RESERVED = [
        'admin', 'administrator', 'api', 'www', 'app', 'me', 'auth', 'login',
        'logout', 'register', 'profiles', 'profile', 'settings', 'support',
        'help', 'about', 'root', 'system', 'moderator', 'official', 'staff',
        'security', 'terms', 'privacy', 'explore', 'search', 'notifications',
        'messages', 'feed', 'home',
    ];

    /**
     * Build a valid, unique handle from a desired value, falling back to
     * the user's name and then the email local part.
     */
    public static function generate(?string $desired, ?string $name = null, ?string $email = null): string
    {
        $candidates = array_filter([
            $desired,
            $name,
            $email ? Str::before($email, '@') : null,
        ]);

        $base = '';

        foreach ($candidates as $candidate) {
            $base = self::sanitize($candidate);

            if ($base !== '') {
                break;
            }
        }

        if ($base === '') {
            $base = 'user';
        }

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }

        $base = substr($base, 0, 30);

        $handle = in_array($base, self::RESERVED, true) ? null : $base;

        $suffix = 1;

        while ($handle === null || Profile::withTrashed()->where('handle', $handle)->exists()) {
            $suffixString = (string) $suffix;
            $handle = substr($base, 0, 30 - strlen($suffixString)).$suffixString;
            $suffix++;

            if ($suffix > 500) {
                $handle = substr($base, 0, 16).Str::lower(Str::random(8));
            }
        }

        return $handle;
    }

    private static function sanitize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[\s\-\.]+/', '_', $value) ?? '';
        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';

        return trim($value, '_');
    }
}
