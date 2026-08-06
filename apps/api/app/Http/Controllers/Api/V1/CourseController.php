<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Course consumption (Shop P3). The outline is browsable by anyone who can see
 * the product; full lesson content + attachments are gated by the Purchase
 * entitlement (buyer) or store ownership (vendor), except preview lessons.
 */
class CourseController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    /** Curriculum outline (structure only — no gated bodies). */
    public function outline(Request $request, Product $product): JsonResponse
    {
        $profile = $this->activeProfile($request);
        abort_unless($product->isCourse(), 404);
        abort_unless($this->visibleOrOwner($request, $product), 404);

        $product->load('modules.lessons', 'store');
        $owned = $this->canAccess($request, $product);

        $completedIds = $owned ? $this->completedLessonIds($product, $profile) : collect();
        $total = 0;

        $modules = $product->modules->map(function ($module) use ($owned, $completedIds, &$total) {
            return [
                'ulid' => $module->ulid,
                'title' => $module->title,
                'lessons' => $module->lessons->map(function (CourseLesson $lesson) use ($owned, $completedIds, &$total) {
                    $total++;

                    return [
                        'ulid' => $lesson->ulid,
                        'title' => $lesson->title,
                        'minutes' => $lesson->minutes,
                        'is_preview' => $lesson->is_preview,
                        'has_attachment' => $lesson->hasAttachment(),
                        'is_completed' => $owned && $completedIds->contains($lesson->id),
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'data' => [
                'product' => [
                    'ulid' => $product->ulid,
                    'title' => $product->title,
                    'store' => $product->store?->slug,
                ],
                'owned' => $owned,
                'progress' => ['completed' => $completedIds->count(), 'total' => $total],
                'modules' => $modules,
            ],
        ]);
    }

    /** Full lesson content — gated unless owned/owner or a preview lesson. */
    public function lesson(Request $request, CourseLesson $lesson): JsonResponse
    {
        $lesson->load('module.product.store');
        $product = $lesson->module->product;
        $profile = $this->activeProfile($request);

        $owned = $this->canAccess($request, $product);
        abort_unless($owned || $lesson->is_preview, 403, 'Purchase this course to unlock the lesson.');

        return response()->json([
            'data' => [
                'ulid' => $lesson->ulid,
                'title' => $lesson->title,
                'body' => $lesson->body,
                'video_url' => $lesson->video_url,
                'has_attachment' => $lesson->hasAttachment(),
                'minutes' => $lesson->minutes,
                'is_preview' => $lesson->is_preview,
                'is_completed' => $owned && $this->completedLessonIds($product, $profile)->contains($lesson->id),
                'next' => $this->nextLesson($product, $lesson),
            ],
        ]);
    }

    /** Mark a lesson complete for the active profile (idempotent). */
    public function complete(Request $request, CourseLesson $lesson): JsonResponse
    {
        $lesson->load('module.product.store');
        $product = $lesson->module->product;
        $profile = $this->activeProfile($request);

        abort_unless($this->canAccess($request, $product), 403);

        $lesson->completedBy()->syncWithoutDetaching([
            $profile->id => ['completed_at' => now()],
        ]);

        $this->gamification->award($profile, GamificationService::LESSON_COMPLETED, $lesson);

        $completed = $this->completedLessonIds($product, $profile);
        $total = $product->loadMissing('modules.lessons')->modules->sum(fn ($m) => $m->lessons->count());

        return response()->json(['data' => [
            'is_completed' => true,
            'progress' => ['completed' => $completed->count(), 'total' => $total],
        ]]);
    }

    /** Stream a lesson attachment — same gate as the lesson body. */
    public function attachment(Request $request, CourseLesson $lesson): StreamedResponse
    {
        $lesson->load('module.product.store');
        $product = $lesson->module->product;

        abort_unless($this->canAccess($request, $product) || $lesson->is_preview, 403);

        $disk = Storage::disk(config('media.private_disk'));

        abort_unless($lesson->attachment_path !== null && $disk->exists($lesson->attachment_path), 404);

        return $disk->download($lesson->attachment_path, $lesson->title);
    }

    /**
     * @return array{ulid:string, title:string}|null
     */
    private function nextLesson(Product $product, CourseLesson $current): ?array
    {
        $flat = $product->loadMissing('modules.lessons')->modules
            ->flatMap(fn ($m) => $m->lessons)
            ->values();

        $index = $flat->search(fn (CourseLesson $l) => $l->id === $current->id);
        $next = $index === false ? null : $flat->get($index + 1);

        return $next ? ['ulid' => $next->ulid, 'title' => $next->title] : null;
    }

    /**
     * @return Collection<int, int>
     */
    private function completedLessonIds(Product $product, Profile $profile): Collection
    {
        $lessonIds = $product->loadMissing('modules.lessons')->modules
            ->flatMap(fn ($m) => $m->lessons->pluck('id'));

        if ($lessonIds->isEmpty()) {
            return collect();
        }

        return DB::table('course_lesson_progress')
            ->where('profile_id', $profile->id)
            ->whereIn('course_lesson_id', $lessonIds)
            ->pluck('course_lesson_id');
    }

    /** Buyer entitlement OR the vendor acting as the owning store profile. */
    private function canAccess(Request $request, Product $product): bool
    {
        $profile = $request->attributes->get('activeProfile');

        return Purchase::ownedBy($profile, $product)
            || ($product->store !== null && $profile instanceof Profile && $product->store->profile_id === $profile->id);
    }

    private function visibleOrOwner(Request $request, Product $product): bool
    {
        $profile = $request->attributes->get('activeProfile');

        if ($product->is_published && $product->store?->is_active) {
            return true;
        }

        return $product->store !== null && $profile instanceof Profile && $product->store->profile_id === $profile->id;
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
