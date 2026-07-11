<?php

namespace App\Models;

use Database\Factories\XpLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable record of a single XP award. Ledger rows are never updated, so
 * only a created_at timestamp is maintained.
 */
class XpLedgerEntry extends Model
{
    /** @use HasFactory<XpLedgerEntryFactory> */
    use HasFactory;

    protected $table = 'xp_ledger';

    public const UPDATED_AT = null;

    protected $fillable = [
        'profile_id',
        'action_key',
        'points',
        'subject_type',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
