<?php

namespace App\Support;

use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records an audit trail of administrative/moderation actions.
 */
class Moderation
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?Model $target = null, array $meta = [], ?User $actor = null): ModerationAction
    {
        $actor ??= Auth::user();

        return ModerationAction::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
