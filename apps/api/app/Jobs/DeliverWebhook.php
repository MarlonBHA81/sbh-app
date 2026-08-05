<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one webhook payload to one endpoint (CRM/marketing). Generic mode
 * HMAC-signs the body; Brevo mode maps the contact to Brevo's upsert shape and
 * sends the configured api-key header. Retries with backoff on failure.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $payload  the normalised {event, contact, data, sent_at} envelope
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        if (! $this->endpoint->is_active) {
            return;
        }

        [$body, $headers] = $this->endpoint->format === WebhookEndpoint::FORMAT_BREVO
            ? $this->brevo()
            : $this->generic();

        Http::timeout(15)
            ->withHeaders($headers)
            ->post($this->endpoint->url, $body)
            ->throw();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function generic(): array
    {
        $headers = ['X-SBH-Event' => $this->event];

        if ($this->endpoint->secret) {
            $headers['X-SBH-Signature'] = hash_hmac(
                'sha256',
                json_encode($this->payload) ?: '',
                $this->endpoint->secret,
            );
        }

        if ($this->endpoint->header_name) {
            $headers[$this->endpoint->header_name] = (string) $this->endpoint->header_value;
        }

        return [$this->payload, $headers];
    }

    /**
     * Map the contact to Brevo's contact-upsert shape.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function brevo(): array
    {
        $contact = $this->payload['contact'] ?? [];

        $body = array_filter([
            'email' => $contact['email'] ?? null,
            'updateEnabled' => true,
            'attributes' => array_filter([
                'FIRSTNAME' => $contact['name'] ?? null,
                'HANDLE' => $contact['handle'] ?? null,
                'SBH_EVENT' => $this->event,
            ]),
        ], fn ($v) => $v !== null && $v !== []);

        $headers = ['accept' => 'application/json'];
        // Brevo authenticates with an "api-key" header; the admin configures it.
        $name = $this->endpoint->header_name ?: 'api-key';
        if ($this->endpoint->header_value) {
            $headers[$name] = (string) $this->endpoint->header_value;
        }

        return [$body, $headers];
    }
}
