<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    public const KIND_IMPRESSION = 'impression';

    public const KIND_CLICK = 'click';

    public const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id',
        'ad_slot_id',
        'kind',
        'profile_id',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
