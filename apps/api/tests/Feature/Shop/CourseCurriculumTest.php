<?php

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Support\Str;

function courseFixtures(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Academy', 'is_active' => true,
    ]);
    $course = $store->products()->create([
        'type' => 'course', 'title' => 'Grow your shop', 'description' => 'x',
        'price_cents' => 19900, 'is_published' => true,
    ]);

    return [$owner, $business, $store, $course];
}

/** A published course with one module and two lessons (first is a preview). */
function seededCourse(Product $course): array
{
    $module = $course->modules()->create(['title' => 'Module 1', 'position' => 0]);
    $preview = $module->lessons()->create([
        'title' => 'Welcome', 'body' => 'Intro', 'is_preview' => true, 'position' => 0,
    ]);
    $locked = $module->lessons()->create([
        'title' => 'Deep dive', 'body' => 'Secret sauce', 'is_preview' => false, 'position' => 1,
    ]);

    return [$module, $preview, $locked];
}

test('a vendor builds a curriculum and a non-owner cannot', function () {
    [$owner, $business, $store, $course] = courseFixtures();

    $res = $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/me/store/products/{$course->ulid}/modules", ['title' => 'Basics'])
        ->assertCreated();
    $moduleUlid = $res->json('data.ulid');

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/me/store/modules/{$moduleUlid}/lessons", [
            'title' => 'Lesson one', 'body' => 'Body', 'is_preview' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Lesson one');

    expect(CourseModule::query()->count())->toBe(1)
        ->and(CourseLesson::query()->count())->toBe(1);

    // A stranger with their own store can't author another store's course.
    $this->flushHeaders();
    $stranger = userWithProfile();
    $strangerBiz = Profile::factory()->business()->for($stranger)->create();
    Store::create([
        'profile_id' => $strangerBiz->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Other', 'is_active' => true,
    ]);
    $this->actingAs($stranger)
        ->withHeader('X-Profile-Id', $strangerBiz->ulid)
        ->postJson("/api/v1/me/store/products/{$course->ulid}/modules", ['title' => 'Hack'])
        ->assertStatus(403);
});

test('the public outline hides gated bodies but shows preview flags', function () {
    [$owner, $business, $store, $course] = courseFixtures();
    seededCourse($course);

    $shopper = userWithProfile();
    $res = $this->actingAs($shopper)
        ->getJson("/api/v1/shop/products/{$course->ulid}/curriculum")
        ->assertOk()
        ->assertJsonPath('data.owned', false)
        ->assertJsonPath('data.progress.total', 2)
        ->assertJsonPath('data.modules.0.lessons.0.title', 'Welcome')
        ->assertJsonPath('data.modules.0.lessons.0.is_preview', true)
        ->assertJsonPath('data.modules.0.lessons.1.is_preview', false);

    // Bodies are never in the outline.
    expect(json_encode($res->json()))->not->toContain('Secret sauce');
});

test('a non-buyer can read a preview lesson but not a locked one', function () {
    [$owner, $business, $store, $course] = courseFixtures();
    [$module, $preview, $locked] = seededCourse($course);

    $this->flushHeaders();
    $shopper = userWithProfile();

    $this->actingAs($shopper)
        ->getJson("/api/v1/me/courses/lessons/{$preview->ulid}")
        ->assertOk()
        ->assertJsonPath('data.body', 'Intro');

    $this->actingAs($shopper)
        ->getJson("/api/v1/me/courses/lessons/{$locked->ulid}")
        ->assertStatus(403);
});

test('a buyer unlocks lessons, marks progress, and it is idempotent', function () {
    [$owner, $business, $store, $course] = courseFixtures();
    [$module, $preview, $locked] = seededCourse($course);

    $this->flushHeaders();
    $buyer = userWithProfile();
    Purchase::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'product_id' => $course->id,
    ]);

    $this->actingAs($buyer)
        ->getJson("/api/v1/me/courses/lessons/{$locked->ulid}")
        ->assertOk()
        ->assertJsonPath('data.body', 'Secret sauce')
        ->assertJsonPath('data.next', null);

    $this->actingAs($buyer)
        ->postJson("/api/v1/me/courses/lessons/{$preview->ulid}/complete")
        ->assertOk()
        ->assertJsonPath('data.progress.completed', 1)
        ->assertJsonPath('data.progress.total', 2);

    // Re-completing is a no-op on the count.
    $this->actingAs($buyer)
        ->postJson("/api/v1/me/courses/lessons/{$preview->ulid}/complete")
        ->assertOk()
        ->assertJsonPath('data.progress.completed', 1);

    // Owner sees the completion reflected in the outline.
    $this->actingAs($buyer)
        ->getJson("/api/v1/shop/products/{$course->ulid}/curriculum")
        ->assertOk()
        ->assertJsonPath('data.owned', true)
        ->assertJsonPath('data.modules.0.lessons.0.is_completed', true)
        ->assertJsonPath('data.modules.0.lessons.1.is_completed', false);
});

test('the vendor can access their own course content without buying', function () {
    [$owner, $business, $store, $course] = courseFixtures();
    [$module, $preview, $locked] = seededCourse($course);

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson("/api/v1/me/courses/lessons/{$locked->ulid}")
        ->assertOk()
        ->assertJsonPath('data.body', 'Secret sauce');
});
