<?php

namespace App\Services\Esd;

use App\Models\Disbursement;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;

/**
 * Builds the tracking + spend report for a single ESD programme: a headline
 * summary (supplier status mix, milestone completion, planned-vs-actual spend)
 * and per-supplier rows. The rows drive both the Filament CSV export and the
 * corporate web portal (ESD-5). Everything is scoped to the one programme, so a
 * corporate can never see another sponsor's data.
 */
class ProgrammeReport
{
    public function __construct(private readonly Programme $programme) {}

    public static function for(Programme $programme): self
    {
        return new self($programme);
    }

    /**
     * Headline rollup for the programme.
     *
     * @return array{
     *     cohorts: int,
     *     suppliers: int,
     *     supplier_status: array<string, int>,
     *     milestones: array{total: int, complete: int},
     *     disbursed: array{planned_cents: int, actual_cents: int}
     * }
     */
    public function summary(): array
    {
        $enrolmentIds = $this->programme->enrolments()->pluck('supplier_enrolments.id');

        $statusCounts = $this->programme->enrolments()
            ->selectRaw('supplier_enrolments.status as status, count(*) as aggregate')
            ->groupBy('supplier_enrolments.status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'cohorts' => $this->programme->cohorts()->count(),
            'suppliers' => $enrolmentIds->count(),
            'supplier_status' => $statusCounts,
            'milestones' => [
                'total' => ProgrammeMilestone::query()->whereIn('supplier_enrolment_id', $enrolmentIds)->count(),
                'complete' => ProgrammeMilestone::query()->whereIn('supplier_enrolment_id', $enrolmentIds)
                    ->where('status', ProgrammeMilestone::STATUS_COMPLETE)->count(),
            ],
            'disbursed' => [
                'planned_cents' => (int) Disbursement::query()->whereIn('supplier_enrolment_id', $enrolmentIds)
                    ->whereNull('disbursed_at')->sum('amount_cents'),
                'actual_cents' => (int) Disbursement::query()->whereIn('supplier_enrolment_id', $enrolmentIds)
                    ->whereNotNull('disbursed_at')->sum('amount_cents'),
            ],
        ];
    }

    /**
     * One row per enrolled supplier, with its cohort, status, milestone
     * progress and spend.
     *
     * @return list<array{
     *     cohort: string,
     *     supplier: string,
     *     handle: string,
     *     status: string,
     *     milestones_complete: int,
     *     milestones_total: int,
     *     planned_cents: int,
     *     actual_cents: int
     * }>
     */
    public function supplierRows(): array
    {
        $enrolments = $this->programme->enrolments()
            ->with(['cohort:id,name', 'supplier:id,name,handle'])
            ->withCount([
                'milestones as milestones_total',
                'milestones as milestones_complete' => fn ($query) => $query->where('status', ProgrammeMilestone::STATUS_COMPLETE),
            ])
            ->orderBy('cohorts.name')
            ->get();

        return $enrolments->map(fn (SupplierEnrolment $enrolment) => [
            'cohort' => $enrolment->cohort?->name ?? '',
            'supplier' => $enrolment->supplier?->name ?? '',
            'handle' => $enrolment->supplier?->handle ?? '',
            'status' => $enrolment->status,
            'milestones_complete' => (int) $enrolment->milestones_complete,
            'milestones_total' => (int) $enrolment->milestones_total,
            'planned_cents' => $enrolment->plannedDisbursedCents(),
            'actual_cents' => $enrolment->actualDisbursedCents(),
        ])->all();
    }

    /** Header + data columns for the supplier-level CSV export. */
    public function toCsv(): string
    {
        $header = [
            'Cohort', 'Supplier', 'Handle', 'Status',
            'Milestones complete', 'Milestones total',
            'Planned (ZAR)', 'Disbursed (ZAR)',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header, ',', '"', '');

        foreach ($this->supplierRows() as $row) {
            fputcsv($handle, [
                $row['cohort'],
                $row['supplier'],
                $row['handle'],
                $row['status'],
                $row['milestones_complete'],
                $row['milestones_total'],
                number_format($row['planned_cents'] / 100, 2, '.', ''),
                number_format($row['actual_cents'] / 100, 2, '.', ''),
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
