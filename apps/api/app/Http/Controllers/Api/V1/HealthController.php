<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\System\HealthReport;
use Illuminate\Http\JsonResponse;

/**
 * Deep readiness probe: checks the database, cache, storage and queue and
 * returns 200 when the critical dependencies are healthy, 503 otherwise — so
 * load balancers and uptime monitors can react. Distinct from Laravel's shallow
 * `/up` (framework-boot only) and the public `/status` flags.
 */
class HealthController extends Controller
{
    public function __invoke(HealthReport $report): JsonResponse
    {
        $result = $report->run();

        return response()->json([
            'status' => $result['status'],
            'checks' => $result['checks'],
            'time' => now()->toIso8601String(),
        ], $result['healthy'] ? 200 : 503);
    }
}
