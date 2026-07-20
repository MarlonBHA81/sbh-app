<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A configured outbound webhook target (CRM/marketing). See WebhookDispatcher.
 */
class WebhookEndpoint extends Model
{
    public const FORMAT_GENERIC = 'generic';

    public const FORMAT_BREVO = 'brevo';

    protected $fillable = [
        'name',
        'url',
        'format',
        'secret',
        'header_name',
        'header_value',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether this endpoint wants the given event (empty subscription = all). */
    public function wantsEvent(string $event): bool
    {
        $events = $this->events ?? [];

        return $events === [] || in_array($event, $events, true);
    }
}
