<?php

use App\Observability\SentryScrubber;
use Sentry\Event;
use Sentry\EventId;

test('the scrubber redacts PII and strips auth headers from the request', function () {
    $event = Event::createEvent(new EventId(str_repeat('a', 32)));
    $event->setRequest([
        'url' => 'https://sbh.test/api/v1/me',
        'headers' => [
            'Authorization' => 'Bearer secret-token',
            'Cookie' => 'session=abc',
            'Accept' => 'application/json',
        ],
        'cookies' => ['session' => 'abc'],
        'data' => [
            'email' => 'user@example.com',
            'phone' => '+27820000000',
            'name' => 'Kept',
            'nested' => ['id_number' => '9001015000088', 'ok' => 'value'],
        ],
    ]);
    $event->setExtra(['password' => 'hunter2', 'safe' => 'ok']);

    $scrubbed = SentryScrubber::beforeSend($event);
    $request = $scrubbed->getRequest();

    expect($request['headers'])->not->toHaveKey('Authorization')
        ->and($request['headers'])->not->toHaveKey('Cookie')
        ->and($request['headers'])->toHaveKey('Accept')
        ->and($request)->not->toHaveKey('cookies')
        ->and($request['data']['email'])->toBe('[redacted]')
        ->and($request['data']['phone'])->toBe('[redacted]')
        ->and($request['data']['name'])->toBe('Kept')
        ->and($request['data']['nested']['id_number'])->toBe('[redacted]')
        ->and($request['data']['nested']['ok'])->toBe('value')
        ->and($scrubbed->getExtra()['password'])->toBe('[redacted]')
        ->and($scrubbed->getExtra()['safe'])->toBe('ok');
});
