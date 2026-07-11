<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    /**
     * Lightweight public status the frontend polls for global flags.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'maintenance_message' => Setting::get('maintenance_message', ''),
            'registration_open' => (bool) Setting::get('registration_open', true),
        ]);
    }
}
