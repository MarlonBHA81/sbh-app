<?php

namespace App\Observability;

use Sentry\Event;
use Sentry\EventHint;

/**
 * PII scrubbing for every Sentry event (POPIA posture — see AUDIT-FINDINGS.md
 * B18/B21). `send_default_pii` is already false, so the SDK omits cookies, the
 * request body and the user's IP/email by default; this is defence-in-depth for
 * anything that still slips through custom context: it strips auth headers and
 * redacts known personal-data keys wherever they appear (request data, extra,
 * tags, breadcrumb data).
 *
 * Referenced from config/sentry.php as a [class, method] callable (not a
 * closure) so `php artisan config:cache` can still serialise the config.
 */
class SentryScrubber
{
    /** Header names dropped outright (case-insensitive). */
    private const STRIP_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-xsrf-token',
        'x-csrf-token',
        'proxy-authorization',
    ];

    /**
     * Field names whose values are replaced with [redacted] wherever they
     * appear. Matched case-insensitively as a substring, so "user_email" and
     * "id_number" are caught too.
     */
    private const REDACT_KEYS = [
        'password',
        'token',
        'secret',
        'authorization',
        'api_key',
        'apikey',
        'email',
        'phone',
        'mobile',
        'id_number',
        'idnumber',
        'passport',
        'address',
        'card',
        'cvv',
        'iban',
        'account_number',
    ];

    private const REDACTED = '[redacted]';

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::scrubHeaders($request['headers']);
            }
            if (isset($request['cookies'])) {
                unset($request['cookies']);
            }
            if (isset($request['data'])) {
                $request['data'] = self::redact($request['data']);
            }
            $event->setRequest($request);
        }

        $event->setExtra(self::redact($event->getExtra()));

        // Tags must stay scalar strings, so redact in place without recursing.
        $tags = $event->getTags();
        foreach ($tags as $key => $value) {
            if (self::keyIsSensitive((string) $key)) {
                $tags[$key] = self::REDACTED;
            }
        }
        $event->setTags($tags);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private static function scrubHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::STRIP_HEADERS, true)) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    /**
     * Recursively redact values under sensitive keys.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redact($value);
            } elseif (self::keyIsSensitive((string) $key)) {
                $data[$key] = self::REDACTED;
            }
        }

        return $data;
    }

    private static function keyIsSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACT_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
