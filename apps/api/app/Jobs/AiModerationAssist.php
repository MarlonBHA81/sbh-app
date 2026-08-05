<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Services\Ai\AiGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs the reported content through the AI gateway and stores the advisory
 * assessment on the report for the moderation queue. A no-op when the gateway
 * is disabled or the content has since disappeared.
 */
class AiModerationAssist implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report) {}

    public function handle(AiGateway $ai): void
    {
        if (! $ai->enabled()) {
            return;
        }

        $report = $this->report->fresh();

        if ($report === null) {
            return;
        }

        $text = $this->contentText($report->reportable);

        if ($text === '') {
            return;
        }

        $result = $ai->moderateText($text);

        if ($result === null) {
            return;
        }

        $report->forceFill(['ai_assessment' => $result->toArray()])->save();
    }

    /**
     * Extract the moderatable text from the reported subject.
     */
    private function contentText(?Model $reportable): string
    {
        return trim(match (true) {
            $reportable instanceof Post => (string) ($reportable->body ?? ''),
            $reportable instanceof Comment => (string) ($reportable->body ?? ''),
            $reportable instanceof Profile => trim(($reportable->name ?? '').' '.($reportable->bio ?? '')),
            default => '',
        });
    }
}
