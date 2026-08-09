<?php

namespace App\Services\Business;

use App\Contracts\CipcVerifier;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Calls a CIPC lookup HTTP API (CIPC eServices or a third-party aggregator).
 * Providers differ, so this maps a small, tolerant set of response shapes:
 * a truthy `found`/`registered`/`exists`, plus `registered_name`/`name` and a
 * `status`. Any failure yields "unavailable" (never throws) so a lookup outage
 * never blocks the caller. Adapt the request/response mapping to your provider.
 */
class HttpCipcVerifier implements CipcVerifier
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout,
    ) {}

    public function lookup(string $registrationNumber): CipcResult
    {
        $registrationNumber = trim($registrationNumber);

        if ($this->baseUrl === '') {
            return CipcResult::unavailable('not_configured');
        }

        try {
            $request = Http::timeout($this->timeout)
                ->acceptJson();

            if ($this->token) {
                $request = $request->withToken($this->token);
            }

            $response = $request->get(rtrim($this->baseUrl, '/').'/companies', [
                'registration_number' => $registrationNumber,
            ]);

            if ($response->status() === 404) {
                return CipcResult::notFound();
            }

            if (! $response->successful()) {
                return CipcResult::unavailable('http_'.$response->status());
            }

            return $this->interpret($response->json());
        } catch (Throwable $e) {
            return CipcResult::unavailable('request_failed');
        }
    }

    /** @param mixed $body */
    private function interpret($body): CipcResult
    {
        if (! is_array($body)) {
            return CipcResult::unavailable('bad_response');
        }

        $found = (bool) ($body['found'] ?? $body['registered'] ?? $body['exists'] ?? false);

        if (! $found) {
            return CipcResult::notFound();
        }

        return CipcResult::verified(
            registeredName: $body['registered_name'] ?? $body['name'] ?? null,
            companyStatus: $body['status'] ?? $body['company_status'] ?? null,
        );
    }
}
