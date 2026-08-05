<?php

use App\Jobs\DeliverWebhook;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

function makeEndpoint(array $attributes = []): WebhookEndpoint
{
    return WebhookEndpoint::create(array_merge([
        'name' => 'CRM',
        'url' => 'https://crm.test/hook',
        'format' => WebhookEndpoint::FORMAT_GENERIC,
        'is_active' => true,
    ], $attributes));
}

test('creating a profile dispatches a contact.created webhook to subscribed endpoints', function () {
    makeEndpoint();
    Bus::fake();

    userWithProfile();

    Bus::assertDispatched(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === WebhookDispatcher::CONTACT_CREATED);
});

test('no webhook is dispatched when there are no active endpoints', function () {
    makeEndpoint(['is_active' => false]);
    Bus::fake();

    userWithProfile();

    Bus::assertNotDispatched(DeliverWebhook::class);
});

test('an endpoint only receives events it subscribes to', function () {
    makeEndpoint(['events' => [WebhookDispatcher::PURCHASE_COMPLETED]]);
    Bus::fake();

    userWithProfile();

    // It wants purchase events, not contact.created.
    Bus::assertNotDispatched(DeliverWebhook::class);
});

test('updating a profile emits contact.updated', function () {
    $user = userWithProfile();
    makeEndpoint();
    Bus::fake();

    $this->actingAs($user)
        ->patchJson("/api/v1/me/profiles/{$user->profiles()->first()->ulid}", ['name' => 'Renamed'])
        ->assertOk();

    Bus::assertDispatched(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === WebhookDispatcher::CONTACT_UPDATED);
});

test('the generic delivery signs the body with the secret', function () {
    Http::fake();
    $endpoint = makeEndpoint(['secret' => 'shh']);
    $payload = ['event' => 'contact.created', 'contact' => ['email' => 'a@b.test'], 'data' => [], 'sent_at' => now()->toIso8601String()];

    (new DeliverWebhook($endpoint, 'contact.created', $payload))->handle();

    Http::assertSent(function ($request) use ($payload) {
        $expected = hash_hmac('sha256', json_encode($payload), 'shh');

        return $request->url() === 'https://crm.test/hook'
            && $request->hasHeader('X-SBH-Signature', $expected)
            && $request['contact']['email'] === 'a@b.test';
    });
});

test('the brevo delivery maps the contact and sends the api key header', function () {
    Http::fake();
    $endpoint = makeEndpoint([
        'url' => 'https://api.brevo.test/v3/contacts',
        'format' => WebhookEndpoint::FORMAT_BREVO,
        'header_name' => 'api-key',
        'header_value' => 'xkeysib-123',
    ]);
    $payload = [
        'event' => 'contact.created',
        'contact' => ['email' => 'z@b.test', 'name' => 'Zola', 'handle' => 'zola'],
        'data' => [],
        'sent_at' => now()->toIso8601String(),
    ];

    (new DeliverWebhook($endpoint, 'contact.created', $payload))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.brevo.test/v3/contacts'
            && $request->hasHeader('api-key', 'xkeysib-123')
            && $request['email'] === 'z@b.test'
            && $request['attributes']['FIRSTNAME'] === 'Zola'
            && $request['updateEnabled'] === true;
    });
});
