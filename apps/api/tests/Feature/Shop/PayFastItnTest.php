<?php

use App\Jobs\DeliverWebhook;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sign an ITN payload exactly like PayFastDriver::build() — key=urlencode(trim)
 * pairs (excluding empty/signature) joined by &, optional passphrase, md5.
 *
 * @param  array<string, mixed>  $data
 */
function pfSign(array $data, ?string $passphrase = null): string
{
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature' || $value === null || $value === '') {
            continue;
        }
        $pairs[] = $key.'='.urlencode(trim((string) $value));
    }
    $str = implode('&', $pairs);
    if (! empty($passphrase)) {
        $str .= '&passphrase='.urlencode(trim($passphrase));
    }

    return md5($str);
}

/** A pending order ready to be confirmed. */
function pendingOrder(int $cents = 9900): Order
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'S', 'is_active' => true,
    ]);
    $product = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Pack', 'description' => 'x',
        'price_cents' => $cents, 'is_published' => true,
    ]);

    $buyer = userWithProfile();
    $order = Order::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'store_id' => $store->id,
        'status' => Order::STATUS_PENDING,
        'total_cents' => $cents,
        'currency' => 'ZAR',
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'title' => $product->title,
        'unit_cents' => $cents,
        'kind' => OrderItem::KIND_ITEM,
    ]);

    return $order->fresh('items');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function itnPayload(Order $order, array $overrides = []): array
{
    $amount = number_format($order->total_cents / 100, 2, '.', '');
    $data = array_merge([
        'm_payment_id' => $order->reference,
        'pf_payment_id' => '1089250',
        'payment_status' => 'COMPLETE',
        'item_name' => 'Pack',
        'amount_gross' => $amount,
        'amount_fee' => '-2.30',
        'amount_net' => number_format($order->total_cents / 100 - 2.30, 2, '.', ''),
        'merchant_id' => '10000100',
    ], $overrides);

    $data['signature'] = pfSign($data);

    return $data;
}

beforeEach(function () {
    config()->set('payments.driver', 'payfast');
    config()->set('payments.payfast.merchant_id', '10000100');
    config()->set('payments.payfast.merchant_key', '46f0cd694581a');
    config()->set('payments.payfast.passphrase', null);
    config()->set('payments.payfast.sandbox', true);
    config()->set('payments.platform_fee_percent', 10);
});

test('a valid ITN marks the order paid and grants entitlements', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $order = pendingOrder(9900);

    $this->post('/api/v1/shop/payfast/itn', itnPayload($order))->assertOk();

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->pf_payment_id)->toBe('1089250')
        ->and($order->platform_fee_cents)->toBe(990)
        ->and($order->vendor_amount_cents)->toBe(8910)
        ->and($order->paid_at)->not->toBeNull();

    $item = $order->items->first();
    expect(Purchase::query()
        ->where('buyer_profile_id', $order->buyer_profile_id)
        ->where('product_id', $item->product_id)
        ->exists())->toBeTrue();
});

test('the ITN is idempotent — a duplicate does not double-grant', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $order = pendingOrder(9900);
    $payload = itnPayload($order);

    $this->post('/api/v1/shop/payfast/itn', $payload)->assertOk();
    $this->post('/api/v1/shop/payfast/itn', $payload)->assertOk();

    expect(Purchase::query()->where('buyer_profile_id', $order->buyer_profile_id)->count())->toBe(1);
});

test('a bad signature leaves the order pending', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $order = pendingOrder(9900);
    $payload = itnPayload($order);
    $payload['signature'] = str_repeat('0', 32);

    $this->post('/api/v1/shop/payfast/itn', $payload)->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
    expect(Purchase::query()->count())->toBe(0);
});

test('an amount mismatch leaves the order pending', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $order = pendingOrder(9900);
    // Attacker claims to have paid R1.00.
    $payload = itnPayload($order, ['amount_gross' => '1.00']);

    $this->post('/api/v1/shop/payfast/itn', $payload)->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('a non-COMPLETE status leaves the order pending', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $order = pendingOrder(9900);
    $payload = itnPayload($order, ['payment_status' => 'FAILED']);

    $this->post('/api/v1/shop/payfast/itn', $payload)->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('an ITN that fails PayFast postback validation is rejected', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('INVALID', 200)]);

    $order = pendingOrder(9900);

    $this->post('/api/v1/shop/payfast/itn', itnPayload($order))->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('a confirmed purchase dispatches the CRM webhook', function () {
    Http::fake(['sandbox.payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    // An active endpoint so the dispatcher has something to fan out to.
    WebhookEndpoint::create([
        'name' => 'CRM',
        'url' => 'https://crm.test/hook',
        'format' => WebhookEndpoint::FORMAT_GENERIC,
        'events' => [WebhookDispatcher::PURCHASE_COMPLETED],
        'is_active' => true,
    ]);

    Bus::fake();

    $order = pendingOrder(9900);
    $this->post('/api/v1/shop/payfast/itn', itnPayload($order))->assertOk();

    Bus::assertDispatched(
        DeliverWebhook::class,
        fn (DeliverWebhook $job) => $job->event === WebhookDispatcher::PURCHASE_COMPLETED,
    );
});
