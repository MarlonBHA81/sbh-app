<?php

namespace App\Services\Tenders;

use App\Models\Opportunity;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ingests open tenders from an OpenProcurement-compatible API into the
 * Opportunity model. Walks the change-feed from a stored offset (so re-runs are
 * incremental), keeps only tenders that are open for bids, and upserts by
 * (source, source_ref) so re-imports update rather than duplicate.
 */
class TenderImporter
{
    /** Feed status that means "open for bids". */
    private const OPEN_STATUS = 'active.tendering';

    private const OFFSET_KEY = 'integrations.tenders.offset';

    public function __construct(private OpenProcurementClient $client) {}

    /**
     * @return array{imported: int, skipped: int, pages: int}
     */
    public function import(?int $maxPages = null, bool $reset = false): array
    {
        $source = (string) config('tenders.source', 'OpenProcurement');
        $publish = (bool) config('tenders.publish', true);
        $detailCap = (int) config('tenders.page_limit', 200);
        $maxPages ??= 50;

        $offset = $reset ? null : (Setting::get(self::OFFSET_KEY) ?: null);

        $imported = 0;
        $skipped = 0;
        $pages = 0;
        $details = 0;

        while ($pages < $maxPages && $details < $detailCap) {
            $page = $this->client->feed($offset);
            $pages++;

            if ($page['items'] === []) {
                break; // caught up with the feed
            }

            foreach ($page['items'] as $item) {
                if ($details >= $detailCap) {
                    break;
                }

                $details++;
                $detail = $this->client->tender($item['id']);

                if ($detail === null || ! $this->isOpen($detail)) {
                    $skipped++;

                    continue;
                }

                Opportunity::updateOrCreate(
                    ['source' => $source, 'source_ref' => $item['id']],
                    $this->map($detail, $item['id'], $source, $publish),
                );
                $imported++;
            }

            $next = $page['offset'];
            // Stop if the feed can't advance (no next offset, or it didn't move).
            if ($next === null || $next === '' || $next === $offset) {
                $offset = $next ?: $offset;
                break;
            }
            $offset = $next;
        }

        if ($offset !== null && $offset !== '') {
            Setting::set(self::OFFSET_KEY, $offset);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'pages' => $pages];
    }

    /** Open for bids and not past its closing date. */
    private function isOpen(array $detail): bool
    {
        if (($detail['status'] ?? null) !== self::OPEN_STATUS) {
            return false;
        }

        $closes = $this->closesAt($detail);

        return $closes !== null && ! $closes->isPast();
    }

    /**
     * @return array<string, mixed>
     */
    private function map(array $detail, string $id, string $source, bool $publish): array
    {
        return [
            'type' => 'tender',
            'title' => $this->str($detail['title'] ?? null) ?: 'Tender',
            'description' => $this->str($detail['description'] ?? null)
                ?: ($this->str($detail['title'] ?? null) ?: 'See the source listing for full details.'),
            'organisation' => $this->str($detail['procuringEntity']['name'] ?? null),
            'source' => $source,
            'source_url' => $this->client->tenderUri($id),
            'is_official' => true,
            'industry' => null,
            'province' => $this->str($detail['procuringEntity']['address']['region'] ?? null),
            'amount' => $this->amount($detail['value'] ?? null),
            'closes_at' => $this->closesAt($detail)?->toDateString(),
            'is_published' => $publish,
        ];
    }

    private function closesAt(array $detail): ?CarbonInterface
    {
        $end = $detail['tenderPeriod']['endDate'] ?? null;

        if (! is_string($end) || $end === '') {
            return null;
        }

        try {
            return Carbon::parse($end);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * OpenProcurement fields are usually plain strings but can arrive as a
     * multilingual object; pick a sensible string either way.
     */
    private function str(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) ?: null;
        }

        if (is_array($value)) {
            $pick = $value['en'] ?? reset($value);

            return is_string($pick) ? (trim($pick) ?: null) : null;
        }

        return null;
    }

    private function amount(mixed $value): ?string
    {
        if (! is_array($value) || ! isset($value['amount'])) {
            return null;
        }

        $currency = isset($value['currency']) ? (string) $value['currency'].' ' : '';

        return $currency.number_format((float) $value['amount']);
    }
}
