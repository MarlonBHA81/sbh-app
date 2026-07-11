<?php

namespace App\Http\Requests\Concerns;

use App\Services\Posts\PostService;
use Closure;
use Illuminate\Support\Carbon;

trait ValidatesScheduledAt
{
    /**
     * Rule closure asserting the value is a future moment when interpreted
     * in the requesting user's timezone.
     */
    protected function futureInUserTimezone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $timezone = $this->user()?->timezone ?: PostService::DEFAULT_TIMEZONE;

            try {
                $scheduled = Carbon::parse((string) $value, $timezone);
            } catch (\Throwable) {
                $fail('The :attribute is not a valid date.');

                return;
            }

            if ($scheduled->utc()->lte(now())) {
                $fail('The :attribute must be in the future.');
            }
        };
    }
}
