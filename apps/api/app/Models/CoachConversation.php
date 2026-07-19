<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's AI Coach conversation (V2 · LEARN). One per profile in v1.
 */
class CoachConversation extends Model
{
    protected $fillable = ['profile_id'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CoachMessage::class)->orderBy('id');
    }
}
