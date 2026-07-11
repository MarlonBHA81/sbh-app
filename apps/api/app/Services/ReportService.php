<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportService
{
    /** Map of the public reportable_type value to its model class. */
    private const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        'profile' => Profile::class,
    ];

    /**
     * File a report. Returns [report, wasCreated]. When an open report by the
     * same reporter against the same target already exists it is returned
     * unchanged (dedupe) with wasCreated = false.
     *
     * @param  array{reportable_type: string, reportable_ulid: string, category: string, details?: string|null}  $data
     * @return array{0: Report, 1: bool}
     */
    public function create(Profile $reporter, array $data): array
    {
        $reportable = $this->resolveReportable(
            $reporter,
            $data['reportable_type'],
            $data['reportable_ulid'],
        );

        $existing = Report::query()
            ->where('reporter_profile_id', $reporter->id)
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->getKey())
            ->whereIn('status', Report::OPEN_STATUSES)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $report = Report::query()->create([
            'reporter_profile_id' => $reporter->id,
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
            'category' => $data['category'],
            'details' => $data['details'] ?? null,
            'status' => Report::STATUS_PENDING,
        ]);

        return [$report, true];
    }

    /**
     * Resolve and authorize the reported subject. Anything the reporter cannot
     * see (missing, hidden, or blocked) is reported as not found.
     */
    private function resolveReportable(Profile $reporter, string $type, string $ulid): Model
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null) {
            throw new NotFoundHttpException('Unknown reportable type.');
        }

        $model = $class::query()->where('ulid', $ulid)->first();

        if ($model === null || ! $this->visibleToReporter($reporter, $model)) {
            throw new NotFoundHttpException('The reported content could not be found.');
        }

        return $model;
    }

    private function visibleToReporter(Profile $reporter, Model $model): bool
    {
        return match (true) {
            $model instanceof Post => $model->isVisibleTo($reporter),
            $model instanceof Comment => $model->post !== null
                && $model->post->isVisibleTo($reporter)
                && ! $model->trashed(),
            $model instanceof Profile => $model->id !== $reporter->id
                && ! app(SafetyService::class)->isBlockedBetween($reporter, $model),
            default => false,
        };
    }
}
