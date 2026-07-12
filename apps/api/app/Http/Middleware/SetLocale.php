<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale for user-facing API strings. An authenticated
 * user's stored preference wins; otherwise the first supported language in the
 * Accept-Language header is used, falling back to the app default (en).
 */
class SetLocale
{
    /** Languages the API ships translations for. */
    public const SUPPORTED = ['en', 'bn', 'ar', 'es', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->fromUser($request) ?? $this->fromHeader($request);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function fromUser(Request $request): ?string
    {
        $locale = $request->user('sanctum')?->locale;

        return $this->supported($locale);
    }

    private function fromHeader(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            // Normalise e.g. "en_US" / "en-GB" to its primary subtag.
            $primary = strtolower(explode('_', str_replace('-', '_', $language))[0]);

            if ($this->supported($primary) !== null) {
                return $primary;
            }
        }

        return null;
    }

    private function supported(?string $locale): ?string
    {
        return in_array($locale, self::SUPPORTED, true) ? $locale : null;
    }
}
