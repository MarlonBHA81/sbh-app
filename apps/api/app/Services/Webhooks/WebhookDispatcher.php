<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\Profile;
use App\Models\WebhookEndpoint;

/**
 * Fans an activity event out to every active webhook endpoint that subscribes to
 * it (CRM/marketing sync). A no-op when nothing is configured, so it's cheap to
 * call from anywhere in the app.
 */
class WebhookDispatcher
{
    public const CONTACT_CREATED = 'contact.created';

    public const CONTACT_UPDATED = 'contact.updated';

    public const PURCHASE_COMPLETED = 'purchase.completed';

    /**
     * @param  array<string, mixed>  $contact  {email, name, handle, ...}
     * @param  array<string, mixed>  $data     event-specific extra data
     */
    public function dispatch(string $event, array $contact, array $data = []): void
    {
        $endpoints = WebhookEndpoint::query()->active()->get()
            ->filter(fn (WebhookEndpoint $e) => $e->wantsEvent($event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = [
            'event' => $event,
            'contact' => $contact,
            'data' => $data,
            'sent_at' => now()->toIso8601String(),
        ];

        foreach ($endpoints as $endpoint) {
            DeliverWebhook::dispatch($endpoint, $event, $payload);
        }
    }

    /** Convenience: emit a contact event from a profile + its owning user. */
    public function contact(string $event, Profile $profile, array $data = []): void
    {
        $this->dispatch($event, [
            'email' => $profile->user?->email,
            'name' => $profile->name,
            'handle' => $profile->handle,
        ], $data);
    }
}
