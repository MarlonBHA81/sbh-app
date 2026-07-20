<?php

namespace App\Observers;

use App\Models\Profile;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Emits a `contact.created` webhook when a profile (a "contact") is created, so a
 * CRM (Brevo or any) can sync new members. Updates are emitted explicitly from
 * the profile-update endpoint to avoid firing on internal counter saves.
 */
class ProfileObserver
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function created(Profile $profile): void
    {
        $profile->loadMissing('user');

        $this->webhooks->contact(WebhookDispatcher::CONTACT_CREATED, $profile, [
            'kind' => $profile->kind,
        ]);
    }
}
