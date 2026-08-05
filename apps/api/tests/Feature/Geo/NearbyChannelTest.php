<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Pull the registered authorization closure for the nearby presence channel
 * straight off the broadcaster, so we can exercise the precision guard and the
 * member payload without a live websocket handshake.
 */
function nearbyChannelCallback(): Closure
{
    $broadcaster = Broadcast::driver();
    $property = (new ReflectionClass($broadcaster))->getProperty('channels');
    $property->setAccessible(true);

    /** @var array<string, Closure> $channels */
    $channels = $property->getValue($broadcaster);

    return $channels['nearby.{geohash}'];
}

test('the nearby channel rejects geohashes that are not precision 4', function () {
    $callback = nearbyChannelCallback();
    $user = userWithProfile();
    request()->headers->set('X-Profile-Id', $user->personalProfile->ulid);

    expect($callback($user, 'abc'))->toBeFalse();       // precision 3
    expect($callback($user, 'abcde'))->toBeFalse();      // precision 5
    expect($callback($user, 'AB!!'))->toBeFalse();       // invalid characters
});

test('the nearby channel admits an active profile with a lite payload', function () {
    $callback = nearbyChannelCallback();
    $user = userWithProfile(['handle' => 'wanderer', 'name' => 'Wanderer']);
    request()->headers->set('X-Profile-Id', $user->personalProfile->ulid);

    $payload = $callback($user, 'r3gx');

    expect($payload)->toBeArray()
        ->toHaveKeys(['ulid', 'handle', 'name', 'avatar_url']);
    expect($payload['handle'])->toBe('wanderer');
});
