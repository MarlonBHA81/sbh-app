<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    protected $table = 'consents';

    public const CHOICE_ACCEPTED = 'accepted';

    public const CHOICE_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'type',
        'policy_version',
        'choice',
        'categories',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
