<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Official opportunity ingestion (V3 · GROW).
 *
 * This command imports opportunities from a *structured JSON file* and upserts
 * them keyed on (source, source_ref) so re-runs update rather than duplicate.
 * It intentionally does NOT scrape or call any third-party API — the live
 * fetching/normalisation is left as a documented backend task so we never ship
 * brittle or unverified scraping.
 *
 * Expected file shape: a JSON array of objects, each with at least `title`,
 * `description` and `source_ref`; optional `type`, `organisation`, `url`,
 * `source_url`, `industry`, `province`, `amount`, `closes_at`.
 *
 * // BACKEND-TODO: add per-provider adapters (e.g. eTenders, SEDA, grant
 * // portals) that fetch upstream listings, normalise them into the array shape
 * // this command consumes, and schedule this import. Verify each provider is an
 * // official source before setting is_official=true, and respect each site's
 * // terms/robots. Keep the (source, source_ref) unique key as the dedupe anchor.
 */
class ImportOfficialOpportunities extends Command
{
    protected $signature = 'opportunities:import
        {file : Path to a JSON file of opportunities to import}
        {--source= : Source label to stamp on every row (e.g. "eTenders")}
        {--official : Mark imported rows as official (verified source)}
        {--publish : Publish imported rows immediately (default: draft)}';

    protected $description = 'Import official opportunities from a structured JSON file (upsert by source + source_ref). Does not scrape third-party sites — see class docblock.';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_string($file) || ! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($file), true);

        if (! is_array($rows)) {
            $this->error('Expected a JSON array of opportunities.');

            return self::FAILURE;
        }

        $source = $this->option('source') ?: 'Manual';
        $official = (bool) $this->option('official');
        $publish = (bool) $this->option('publish');

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if (! is_array($row) || empty($row['title']) || empty($row['description'])) {
                $this->warn("Row {$i}: missing title/description — skipped.");
                $skipped++;

                continue;
            }

            $type = $row['type'] ?? 'programme';
            if (! in_array($type, Opportunity::TYPES, true)) {
                $type = 'programme';
            }

            $ref = $row['source_ref'] ?? null;

            $attributes = [
                'type' => $type,
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'organisation' => Arr::get($row, 'organisation'),
                'url' => Arr::get($row, 'url'),
                'source' => $source,
                'source_url' => Arr::get($row, 'source_url'),
                'is_official' => $official,
                'industry' => Arr::get($row, 'industry'),
                'province' => Arr::get($row, 'province'),
                'amount' => Arr::get($row, 'amount'),
                'closes_at' => Arr::get($row, 'closes_at'),
                'is_published' => $publish,
            ];

            // Upsert by (source, source_ref) when a ref is present; otherwise
            // always insert (no stable key to dedupe against).
            if ($ref !== null) {
                Opportunity::updateOrCreate(
                    ['source' => $source, 'source_ref' => (string) $ref],
                    $attributes,
                );
            } else {
                Opportunity::create($attributes);
            }

            $imported++;
        }

        $this->info("Imported/updated {$imported} opportunity(ies) from \"{$source}\"; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
