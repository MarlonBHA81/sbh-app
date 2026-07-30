<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a string attribute at rest (APP_KEY), tolerating legacy plaintext
 * rows the same way as EncryptedJson: decrypt on read, fall back to plaintext
 * on failure, always re-encrypt on write. Used for webhook endpoint secrets and
 * bearer/header values.
 *
 * @implements CastsAttributes<?string, ?string>
 */
class EncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value; // legacy plaintext
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString($value);
    }
}
