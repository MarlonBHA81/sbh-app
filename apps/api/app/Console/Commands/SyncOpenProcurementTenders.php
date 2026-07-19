<?php

namespace App\Console\Commands;

use App\Services\Tenders\OpenProcurementClient;
use App\Services\Tenders\TenderImporter;
use Illuminate\Console\Command;

/**
 * Sync open tenders from an OpenProcurement-compatible API into the Opportunity
 * feed (type = tender). Incremental via a stored feed offset; idempotent by
 * (source, source_ref). Scheduled hourly; no-op unless enabled in config.
 */
class SyncOpenProcurementTenders extends Command
{
    protected $signature = 'tenders:sync
        {--reset : Ignore the stored feed offset and start from the beginning}
        {--pages= : Cap the number of feed pages walked this run}';

    protected $description = 'Import open tenders from an OpenProcurement API into the Opportunity feed';

    public function handle(): int
    {
        if (! config('tenders.enabled')) {
            $this->warn('Tender sync is disabled (set OPENPROCUREMENT_ENABLED=true).');

            return self::SUCCESS;
        }

        $pages = $this->option('pages');
        $maxPages = is_numeric($pages) ? max(1, (int) $pages) : null;

        $importer = new TenderImporter(OpenProcurementClient::fromConfig());

        $result = $importer->import($maxPages, (bool) $this->option('reset'));

        $this->info(sprintf(
            'Tenders sync: %d imported/updated, %d skipped, across %d feed page(s).',
            $result['imported'],
            $result['skipped'],
            $result['pages'],
        ));

        return self::SUCCESS;
    }
}
