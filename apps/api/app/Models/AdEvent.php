<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    public const KIND_IMPRESSION = 'impression';

    public const KIND_CLICK = 'click';

    /** An outbound click on the promoted post's link (advertiser site). */
    public const KIND_LINK_CLICK = 'link_click';

    public const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id',
        'ad_slot_id',
        'opportunity_id',
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

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
