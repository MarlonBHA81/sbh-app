<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's role on a (business) profile they help manage/post under (Roles P4).
 */
class ProfileMember extends Model
{
    /** Full control incl. members + settings; the profile creator. */
    public const ROLE_OWNER = 'owner';

    /** May post and manage members (but not delete the profile / remove owner). */
    public const ROLE_MANAGER = 'manager';

    /** May post under the business identity only. */
    public const ROLE_POSTER = 'poster';

    /** Roles a member can be assigned via the API (owner is the creator only). */
    public const ASSIGNABLE_ROLES = [
        self::ROLE_MANAGER,
        self::ROLE_POSTER,
    ];

    protected $fillable = [
        'profile_id',
        'user_id',
        'role',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
