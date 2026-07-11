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
