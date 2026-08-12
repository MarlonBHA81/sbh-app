<?php

namespace App\Services\Business;

use App\Contracts\CipcVerifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The official CIPC "CIPC Public Data - Commercial" API (Azure APIM at
 * apim.cipc.co.za). Verifies a South African enterprise number against the
 * "Basic company information" operation:
 *
 *   POST {base}/information   body: {"enterprise_number": "2020/939681/07"}
 *   headers: Ocp-Apim-Subscription-Key + Authorization: Bearer <token>
 *   200: {"Enterprise": [ { enterprise_name, enterprise_status_description, ... } ]}
 *
 * The Bearer token is either a static configured token or obtained via the
 * OAuth2 client-credentials grant (cached until it expires). Any failure yields
 * "unavailable" — this method never throws.
 */
class CipcApiVerifier implements CipcVerifier
{
    private const TOKEN_CACHE_KEY = 'cipc:access_token';

    /**
     * @param  array<string, mixed>  $config  The config('services.cipc') array.
     */
    public function __construct(private readonly array $config) {}

    public function lookup(string $registrationNumber): CipcResult
    {
        $registrationNumber = trim($registrationNumber);
        $baseUrl = (string) ($this->config['base_url'] ?? '');

        if ($baseUrl === '') {
            return CipcResult::unavailable('not_configured');
        }

        try {
            $request = Http::timeout((int) ($this->config['timeout'] ?? 15))
                ->acceptJson();

            if ($key = $this->config['subscription_key'] ?? null) {
                $request = $request->withHeaders(['Ocp-Apim-Subscription-Key' => $key]);
            }

            if ($token = $this->accessToken()) {
                $request = $request->withToken($token);
            }

            $response = $request->post(rtrim($baseUrl, '/').'/information', [
                'enterprise_number' => $registrationNumber,
            ]);

            if ($response->status() === 401 || $response->status() === 403) {
                return CipcResult::unavailable('unauthorized');
            }

            if (! $response->successful()) {
                return CipcResult::unavailable('http_'.$response->status());
            }

            return $this->interpret($response->json());
        } catch (Throwable) {
            return CipcResult::unavailable('request_failed');
        }
    }

    /**
     * Map the CIPC response. The "Enterprise" payload is a list of matching
     * companies (or a single record); an empty/missing list means not found.
     *
     * @param  mixed  $body
     */
    private function interpret($body): CipcResult
    {
        if (! is_array($body)) {
            return CipcResult::unavailable('bad_response');
        }

        $enterprise = $body['Enterprise'] ?? $body['enterprise'] ?? null;

        if (is_array($enterprise) && array_is_list($enterprise)) {
            $company = $enterprise[0] ?? null;
        } else {
            $company = $enterprise; // a single associative record, or null
        }

        if (! is_array($company) || $company === []) {
            return CipcResult::notFound();
        }

        return CipcResult::verified(
            registeredName: $company['enterprise_name'] ?? $company['EnterpriseName'] ?? null,
            companyStatus: $company['enterprise_status_description'] ?? $company['EnterpriseStatusDescription'] ?? null,
        );
    }

    /**
     * A Bearer token for the API: a static configured token, or one fetched via
     * the OAuth2 client-credentials grant and cached until just before it
     * expires. Returns null when no auth is configured (the subscription key may
     * suffice for some products).
     */
    private function accessToken(): ?string
    {
        if ($token = $this->config['token'] ?? null) {
            return (string) $token;
        }

        $tokenUrl = $this->config['token_url'] ?? null;
        $clientId = $this->config['client_id'] ?? null;
        $clientSecret = $this->config['client_secret'] ?? null;

        if (! $tokenUrl || ! $clientId || ! $clientSecret) {
            return null;
        }

        return Cache::get(self::TOKEN_CACHE_KEY) ?? $this->fetchAndCacheToken(
            (string) $tokenUrl,
            (string) $clientId,
            (string) $clientSecret,
        );
    }

    private function fetchAndCacheToken(string $tokenUrl, string $clientId, string $clientSecret): ?string
    {
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        if ($scope = $this->config['scope'] ?? null) {
            $params['scope'] = $scope;
        }

        $response = Http::asForm()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->post($tokenUrl, $params);

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        // Cache until ~60s before expiry (default 5 min when not reported).
        $expiresIn = (int) ($response->json('expires_in') ?? 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(30, $expiresIn - 60));

        return $token;
    }
}
