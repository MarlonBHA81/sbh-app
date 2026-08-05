<?php

namespace App\Models;

use Database\Factories\XpActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-configurable definition of an action that can award (or deduct) XP.
 */
class XpAction extends Model
{
    /** @use HasFactory<XpActionFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'points',
        'daily_cap',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'daily_cap' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
