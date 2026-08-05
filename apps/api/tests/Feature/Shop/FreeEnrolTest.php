<?php

use App\Jobs\DeliverWebhook;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

function freeStore(): Store
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    return Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Freebies', 'is_active' => true,
    ]);
}

test('a member can enrol free in a R0 course and gets the entitlement', function () {
    $store = freeStore();
    $course = $store->products()->create([
        'type' => 'course', 'title' => 'Free starter', 'description' => 'x',
        'price_cents' => 0, 'is_published' => true,
    ]);

    $member = userWithProfile();
    $this->actingAs($member)
        ->postJson("/api/v1/shop/products/{$course->ulid}/enrol")
        ->assertOk()
        ->assertJsonPath('data.enrolled', true);

    expect(Purchase::query()
        ->where('buyer_profile_id', $member->profiles()->first()->id)
        ->where('product_id', $course->id)
        ->exists())->toBeTrue();
});

test('free enrolment is idempotent', function () {
    $store = freeStore();
    $course = $store->products()->create([
        'type' => 'course', 'title' => 'Free', 'description' => 'x',
        'price_cents' => null, 'is_published' => true,
    ]);

    $member = userWithProfile();
    $this->actingAs($member)->postJson("/api/v1/shop/products/{$course->ulid}/enrol")->assertOk();
    $this->actingAs($member)->postJson("/api/v1/shop/products/{$course->ulid}/enrol")->assertOk();

    expect(Purchase::query()->where('product_id', $course->id)->count())->toBe(1);
});

test('a paid product cannot be enrolled for free', function () {
    $store = freeStore();
    $paid = $store->products()->create([
        'type' => 'course', 'title' => 'Paid', 'description' => 'x',
        'price_cents' => 4900, 'is_published' => true,
    ]);

    $member = userWithProfile();
    $this->actingAs($member)
        ->postJson("/api/v1/shop/products/{$paid->ulid}/enrol")
        ->assertStatus(422);

    expect(Purchase::query()->count())->toBe(0);
});

test('free enrolment fires the purchase.completed CRM webhook once', function () {
    WebhookEndpoint::create([
        'name' => 'CRM', 'url' => 'https://crm.test/hook',
        'format' => WebhookEndpoint::FORMAT_GENERIC,
        'events' => [WebhookDispatcher::PURCHASE_COMPLETED], 'is_active' => true,
    ]);
    Bus::fake();

    $store = freeStore();
    $course = $store->products()->create([
        'type' => 'course', 'title' => 'Free', 'description' => 'x',
        'price_cents' => 0, 'is_published' => true,
    ]);

    $member = userWithProfile();
    $this->actingAs($member)->postJson("/api/v1/shop/products/{$course->ulid}/enrol")->assertOk();
    $this->actingAs($member)->postJson("/api/v1/shop/products/{$course->ulid}/enrol")->assertOk();

    // Fired once (first enrolment only).
    Bus::assertDispatchedTimes(DeliverWebhook::class, 1);
});
