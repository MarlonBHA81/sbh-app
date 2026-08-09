<?php

use App\Services\System\HealthReport;
use Illuminate\Support\Facades\Storage;

/** A HealthReport stand-in with a fixed verdict. */
function fakeHealthReport(bool $healthy): HealthReport
{
    return new class($healthy) extends HealthReport
    {
        public function __construct(private bool $healthy) {}

        public function run(): array
        {
            return [
                'status' => $this->healthy ? 'ok' : 'error',
                'healthy' => $this->healthy,
                'checks' => [
                    'database' => ['ok' => $this->healthy],
                    'cache' => ['ok' => true],
                    'storage' => ['ok' => true, 'disk' => 'public'],
                    'queue' => ['ok' => true, 'connection' => 'database', 'pending' => 0, 'failed' => 0],
                ],
            ];
        }
    };
}

test('the health endpoint returns 200 and per-check status when healthy', function () {
    Storage::fake('public');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.cache.ok', true)
        ->assertJsonPath('checks.storage.ok', true)
        ->assertJsonStructure(['status', 'time', 'checks' => ['database', 'cache', 'storage', 'queue']]);
});

test('the health endpoint returns 503 when a critical dependency is down', function () {
    app()->instance(HealthReport::class, fakeHealthReport(healthy: false));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('checks.database.ok', false);
});

test('the queue check reports pending and failed counts on the database driver', function () {
    config()->set('queue.default', 'database');
    Storage::fake('public');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('checks.queue.connection', 'database')
        ->assertJsonStructure(['checks' => ['queue' => ['pending', 'failed']]]);
});

test('the system:health command succeeds when healthy', function () {
    Storage::fake('public');

    $this->artisan('system:health')->assertSuccessful();
});

test('the system:health command fails when a critical dependency is down', function () {
    app()->instance(HealthReport::class, fakeHealthReport(healthy: false));

    $this->artisan('system:health')->assertFailed();
});
