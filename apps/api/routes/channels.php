<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
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

// Conversation presence channel: only active participants may join. Returns a
// lightweight profile so members can render a live presence roster.
Broadcast::channel('conversation.{ulid}', function ($user, string $ulid) {
    $conversation = Conversation::query()->where('ulid', $ulid)->first();

    if (! $conversation) {
        return false;
    }

    $profileUlid = request()->header('X-Profile-Id');

    $profile = $profileUlid
        ? $user->profiles()->where('ulid', $profileUlid)->first()
        : $user->personalProfile;

    if (! $profile) {
        return false;
    }

    $participates = ConversationParticipant::query()
        ->where('conversation_id', $conversation->id)
        ->where('profile_id', $profile->id)
        ->whereNull('left_at')
        ->exists();

    if (! $participates) {
        return false;
    }

    return [
        'ulid' => $profile->ulid,
        'handle' => $profile->handle,
        'name' => $profile->name,
        'avatar' => $profile->avatarUrl(),
    ];
});

// Nearby presence channel: keyed by a precision-4 geohash cell (~40 km). Any
// authenticated, active profile may join; membership itself is the "active
// now" signal, so no server-side events are emitted. The member payload is a
// lite profile used to render the live roster.
Broadcast::channel('nearby.{geohash}', function ($user, string $geohash) {
    if (! preg_match('/^[0-9bcdefghjkmnpqrstuvwxyz]{4}$/', $geohash)) {
        return false;
    }

    $profileUlid = request()->header('X-Profile-Id');

    $profile = $profileUlid
        ? $user->profiles()->where('ulid', $profileUlid)->first()
        : $user->personalProfile;

    if (! $profile) {
        return false;
    }

    return [
        'ulid' => $profile->ulid,
        'handle' => $profile->handle,
        'name' => $profile->name,
        'avatar_url' => $profile->avatarUrl(),
    ];
});
