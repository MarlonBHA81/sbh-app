<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records the general audit trail of security-sensitive user actions
 * (authentication, tokens, data-subject rights, consent). Mirrors the
 * Moderation helper but captures the request context (IP + user agent) and is
 * keyed to the acting user rather than an admin decision.
 */
class Activity
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?Model $target = null, array $meta = [], ?User $actor = null): ActivityLog
    {
        $actor ??= Auth::user();

        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
