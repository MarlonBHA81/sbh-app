<?php

namespace App\Services\Tenders;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin read client for an OpenProcurement-compatible API (OCDS / Prozorro
 * family). Reads are public; Basic auth is applied only when configured. All
 * requests retry with backoff on transient failures (429 / 5xx).
 */
class OpenProcurementClient
{
    public function __construct(
        private string $baseUrl,
        private string $version,
        private ?string $auth = null,
        private int $timeout = 20,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('tenders.base_url'),
            (string) config('tenders.version'),
            config('tenders.auth') ?: null,
            (int) config('tenders.timeout', 20),
        );
    }

    /**
     * One page of the tenders change-feed.
     *
     * @return array{items: list<array{id: string, dateModified: ?string}>, offset: ?string}
     */
    public function feed(?string $offset = null): array
    {
        $query = [];
        if ($offset !== null && $offset !== '') {
            $query['offset'] = $offset;
        }

        $body = $this->request()
            ->get($this->url('/tenders'), $query)
            ->throw()
            ->json();

        $items = [];
        foreach ($body['data'] ?? [] as $row) {
            if (! empty($row['id'])) {
                $items[] = [
                    'id' => (string) $row['id'],
                    'dateModified' => $row['dateModified'] ?? null,
                ];
            }
        }

        return [
            'items' => $items,
            'offset' => $body['next_page']['offset'] ?? null,
        ];
    }

    /**
     * Full detail for one tender, or null if it can't be fetched.
     *
     * @return array<string, mixed>|null
     */
    public function tender(string $id): ?array
    {
        $response = $this->request()->get($this->url("/tenders/{$id}"));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /** Public URI for a tender detail (used as source_url). */
    public function tenderUri(string $id): string
    {
        return $this->url("/tenders/{$id}");
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout($this->timeout)
            ->acceptJson()
            // Retry transient failures (429 rate-limit, 5xx) with backoff.
            ->retry(3, 1000, throw: false);

        if ($this->auth) {
            [$user, $pass] = array_pad(explode(':', $this->auth, 2), 2, '');
            $request = $request->withBasicAuth($user, $pass);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return "{$this->baseUrl}/api/{$this->version}".$path;
    }
}
