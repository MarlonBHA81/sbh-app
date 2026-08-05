<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vendor course authoring (Shop P3) — build the modules → lessons curriculum for
 * a `course` product. Gated to the owning business profile's managers.
 */
class StoreCourseController extends Controller
{
    /** The full editable curriculum for one of the vendor's course products. */
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->assertOwns($request, $product);

        $product->load('modules.lessons');

        return response()->json(['data' => $this->serialize($product)]);
    }

    public function storeModule(Request $request, Product $product): JsonResponse
    {
        $this->assertOwns($request, $product);

        $data = $request->validate(['title' => ['required', 'string', 'max:160']]);

        $module = $product->modules()->create([
            'title' => $data['title'],
            'position' => (int) $product->modules()->max('position') + 1,
        ]);

        return response()->json(['data' => ['ulid' => $module->ulid, 'title' => $module->title]], 201);
    }

    public function updateModule(Request $request, CourseModule $module): JsonResponse
    {
        $this->assertOwns($request, $module->product);

        $module->update($request->validate(['title' => ['required', 'string', 'max:160']]));

        return response()->json(['data' => ['ulid' => $module->ulid, 'title' => $module->title]]);
    }

    public function destroyModule(Request $request, CourseModule $module): Response
    {
        $this->assertOwns($request, $module->product);
        $module->delete();

        return response()->noContent();
    }

    public function storeLesson(Request $request, CourseModule $module): JsonResponse
    {
        $this->assertOwns($request, $module->product);

        $data = $this->lessonRules($request);

        $lesson = $module->lessons()->create($data + [
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return response()->json(['data' => $this->lessonData($lesson)], 201);
    }

    public function updateLesson(Request $request, CourseLesson $lesson): JsonResponse
    {
        $this->assertOwns($request, $lesson->module->product);

        $lesson->update($this->lessonRules($request, updating: true));

        return response()->json(['data' => $this->lessonData($lesson->fresh())]);
    }

    public function destroyLesson(Request $request, CourseLesson $lesson): Response
    {
        $this->assertOwns($request, $lesson->module->product);
        $lesson->delete();

        return response()->noContent();
    }

    /** Upload a lesson attachment to the private disk. */
    public function uploadAttachment(Request $request, CourseLesson $lesson): JsonResponse
    {
        $this->assertOwns($request, $lesson->module->product);

        // See StoreProductController::uploadFile — same unrestricted-upload gap.
        $request->validate(['file' => [
            'required',
            'file',
            'mimes:'.config('media.deliverable_mimes'),
            'max:'.config('media.deliverable_max_kb'),
        ]]);

        $path = $request->file('file')->store('courses/'.$lesson->ulid, 'local');
        $lesson->update(['attachment_path' => $path]);

        return response()->json(['data' => ['uploaded' => true]]);
    }

    /** Re-position modules and/or lessons in one call. */
    public function reorder(Request $request, Product $product): JsonResponse
    {
        $this->assertOwns($request, $product);

        $data = $request->validate([
            'modules' => ['sometimes', 'array'],
            'modules.*' => ['string'],
            'lessons' => ['sometimes', 'array'],
            'lessons.*' => ['string'],
        ]);

        foreach (array_values($data['modules'] ?? []) as $i => $ulid) {
            $product->modules()->where('ulid', $ulid)->update(['position' => $i]);
        }

        $moduleIds = $product->modules()->pluck('id');
        foreach (array_values($data['lessons'] ?? []) as $i => $ulid) {
            CourseLesson::query()->where('ulid', $ulid)->whereIn('course_module_id', $moduleIds)
                ->update(['position' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonRules(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'video_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $product): array
    {
        return [
            'product' => ['ulid' => $product->ulid, 'title' => $product->title],
            'modules' => $product->modules->map(fn (CourseModule $m) => [
                'ulid' => $m->ulid,
                'title' => $m->title,
                'lessons' => $m->lessons->map(fn (CourseLesson $l) => $this->lessonData($l))->values(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonData(CourseLesson $lesson): array
    {
        return [
            'ulid' => $lesson->ulid,
            'title' => $lesson->title,
            'body' => $lesson->body,
            'video_url' => $lesson->video_url,
            'minutes' => $lesson->minutes,
            'is_preview' => $lesson->is_preview,
            'has_attachment' => $lesson->hasAttachment(),
        ];
    }

    /** Assert the active profile manages the store that owns this product. */
    private function assertOwns(Request $request, Product $product): void
    {
        $store = $this->vendorStore($request);
        abort_unless($product->store_id === $store->id, 403);
        abort_unless($product->isCourse(), 422, 'Only course products have a curriculum.');
    }

    private function vendorStore(Request $request): Store
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 403, 'Only business profiles can run a store.');
        abort_unless($profile->canManageMembers($request->user()), 403);

        $store = $profile->store;
        abort_unless($store !== null, 422, 'Create your store first.');

        return $store;
    }
}
