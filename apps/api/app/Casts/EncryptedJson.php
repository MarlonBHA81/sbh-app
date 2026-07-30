<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a JSON-serialisable attribute at rest (APP_KEY), while tolerating
 * legacy plaintext rows: on read it tries to decrypt and, if that fails
 * (a value written before encryption was added), falls back to reading the
 * plaintext. Every write re-encrypts, so values upgrade transparently.
 *
 * Used for the settings table, which holds live integration credentials
 * (payment passphrase, provider API keys, SMTP password) that must not sit in
 * clear text where any DB read — a leaked backup, a replica — would expose them.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class EncryptedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            $value = Crypt::decryptString($value);
        } catch (DecryptException) {
            // Legacy plaintext row — read it as-is (it will be re-encrypted on
            // the next write).
        }

        return json_decode($value, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return Crypt::encryptString(json_encode($value));
    }
}
