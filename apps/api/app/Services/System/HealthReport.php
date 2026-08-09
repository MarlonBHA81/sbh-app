<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the deep dependency checks behind the /api/v1/health endpoint and the
 * scheduled `system:health` command. Each check is defensive (never throws)
 * and returns a small, secret-free result. database/cache/storage are the
 * "critical" checks that flip overall health; queue is informational.
 */
class HealthReport
{
    /**
     * @return array{status:string, healthy:bool, checks:array<string,array<string,mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'queue' => $this->queue(),
        ];

        $healthy = $checks['database']['ok']
            && $checks['cache']['ok']
            && $checks['storage']['ok'];

        return [
            'status' => $healthy ? 'ok' : 'error',
            'healthy' => $healthy,
            'checks' => $checks,
        ];
    }

    /** @return array<string,mixed> */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'unreachable'];
        }
    }

    /** @return array<string,mixed> */
    private function cache(): array
    {
        $key = 'health:'.Str::random(10);

        try {
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return ['ok' => $ok];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'unreachable'];
        }
    }

    /** @return array<string,mixed> */
    private function storage(): array
    {
        $disk = (string) config('media.public_disk', 'public');
        $path = 'health/'.Str::random(16).'.txt';

        try {
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);

            return ['ok' => $ok, 'disk' => $disk];
        } catch (Throwable) {
            return ['ok' => false, 'disk' => $disk, 'error' => 'not_writable'];
        }
    }

    /**
     * Queue depth + failed-job count (database driver only). Informational: a
     * backlog or failures don't mark the app unhealthy, but they're surfaced so
     * a monitor can alert.
     *
     * @return array<string,mixed>
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $result = ['ok' => true, 'connection' => $connection];

        if ($connection === 'database') {
            try {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $result['pending'] = DB::table($table)->count();
                $result['failed'] = DB::table('failed_jobs')->count();
            } catch (Throwable) {
                $result['ok'] = false;
                $result['error'] = 'unreadable';
            }
        }

        return $result;
    }
}
