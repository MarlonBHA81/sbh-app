<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in an AI Coach conversation (V2 · LEARN): a member message or the
 * coach's reply.
 */
class CoachMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = ['coach_conversation_id', 'role', 'body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CoachConversation::class, 'coach_conversation_id');
    }
}
