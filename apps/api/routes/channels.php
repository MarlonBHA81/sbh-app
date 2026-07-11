<?php

use App\Models\Profile;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private per-profile channel: only the owning user may subscribe. Used to
// deliver notifications for a specific profile of a multi-profile account.
Broadcast::channel('profile.{ulid}', function ($user, string $ulid) {
    return Profile::query()
        ->where('ulid', $ulid)
        ->where('user_id', $user->id)
        ->exists();
});
