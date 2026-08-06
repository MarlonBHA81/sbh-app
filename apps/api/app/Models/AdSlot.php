<?php

namespace App\Models;

use Database\Factories\AdSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AdSlot extends Model
{
    /** @use HasFactory<AdSlotFactory> */
    use HasFactory;

    public const PLACEMENT_RIGHT_RAIL = 'right_rail';

    public const PLACEMENT_FEED_INLINE = 'feed_inline';

    protected $fillable = [
        'key',
        'placement',
        'name',
        'sponsor_name',
        'sponsor_url',
        'image_path',
        'body',
        'active',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'weight' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(config('media.public_disk'))->url($this->image_path) : null;
    }
}
