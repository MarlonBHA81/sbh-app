<?php

use App\Models\Opportunity;
use App\Models\Setting;
use App\Services\Tenders\OpenProcurementClient;
use App\Services\Tenders\TenderImporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'tenders.enabled' => true,
        'tenders.base_url' => 'https://op.test',
        'tenders.version' => '2.5',
        'tenders.source' => 'OpenProcurement',
        'tenders.page_limit' => 200,
        'tenders.publish' => true,
        'tenders.timeout' => 5,
    ]);
});

function importer(): TenderImporter
{
    return new TenderImporter(OpenProcurementClient::fromConfig());
}

/**
 * Fake the OpenProcurement feed (one page of ids, then empty) and return a
 * detail body per tender id from the provided map.
 *
 * @param  array<string, array<string, mixed>>  $details
 * @param  list<array{id: string}>  $feedItems
 */
function fakeOpenProcurement(array $feedItems, array $details): void
{
    Http::fake(function (Request $request) use ($feedItems, $details) {
        $url = $request->url();

        // Detail: /tenders/{id}
        if (preg_match('#/tenders/([^/?]+)#', $url, $m)) {
            $id = $m[1];

            return isset($details[$id])
                ? Http::response(['data' => $details[$id]], 200)
                : Http::response('', 404);
        }

        // Feed: first call (no offset) returns items, subsequent calls are empty.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (empty($q['offset'])) {
            return Http::response([
                'next_page' => ['offset' => 'OFF1'],
                'data' => $feedItems,
            ], 200);
        }

        return Http::response(['next_page' => ['offset' => 'OFF1'], 'data' => []], 200);
    });
}

function openTender(array $overrides = []): array
{
    return array_merge([
        'status' => 'active.tendering',
        'title' => 'Road works tender',
        'description' => 'Construction of a municipal access road.',
        'value' => ['amount' => 120000, 'currency' => 'ZAR'],
        'tenderPeriod' => ['endDate' => now()->addWeek()->toIso8601String()],
        'procuringEntity' => ['name' => 'City Metro', 'address' => ['region' => 'Gauteng']],
    ], $overrides);
}

test('an open tender is imported and mapped into an opportunity', function () {
    fakeOpenProcurement([['id' => 't1']], ['t1' => openTender()]);

    $result = importer()->import();

    expect($result['imported'])->toBe(1);

    $opp = Opportunity::query()->where('source_ref', 't1')->first();

    expect($opp)->not->toBeNull()
        ->and($opp->type)->toBe('tender')
        ->and($opp->title)->toBe('Road works tender')
        ->and($opp->organisation)->toBe('City Metro')
        ->and($opp->source)->toBe('OpenProcurement')
        ->and($opp->is_official)->toBeTrue()
        ->and($opp->province)->toBe('Gauteng')
        ->and($opp->amount)->toBe('ZAR 120,000')
        ->and($opp->is_published)->toBeTrue()
        ->and($opp->closes_at->isFuture())->toBeTrue();
});

test('re-running upserts by source and ref without duplicating', function () {
    fakeOpenProcurement([['id' => 't1']], ['t1' => openTender()]);

    importer()->import(reset: true);
    importer()->import(reset: true);

    expect(Opportunity::query()->where('source_ref', 't1')->count())->toBe(1);
});

test('closed and past tenders are skipped', function () {
    fakeOpenProcurement(
        [['id' => 'open'], ['id' => 'awarded'], ['id' => 'past']],
        [
            'open' => openTender(),
            'awarded' => openTender(['status' => 'active.awarded']),
            'past' => openTender(['tenderPeriod' => ['endDate' => now()->subDay()->toIso8601String()]]),
        ],
    );

    $result = importer()->import();

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(2)
        ->and(Opportunity::query()->count())->toBe(1)
        ->and(Opportunity::query()->first()->source_ref)->toBe('open');
});

test('the feed offset is persisted so the next run resumes', function () {
    fakeOpenProcurement([['id' => 't1']], ['t1' => openTender()]);

    importer()->import();

    expect(Setting::get('integrations.tenders.offset'))->toBe('OFF1');
});

test('a rate-limited feed request is retried, not fatal', function () {
    // First attempt 429, retry succeeds with an empty feed.
    Http::fake([
        '*/tenders*' => Http::sequence()
            ->push('', 429)
            ->push(['next_page' => ['offset' => ''], 'data' => []], 200),
    ]);

    $result = importer()->import();

    expect($result['imported'])->toBe(0);
});

test('the command is a no-op when disabled', function () {
    config(['tenders.enabled' => false]);

    $this->artisan('tenders:sync')->assertSuccessful();

    expect(Opportunity::query()->count())->toBe(0);
});
