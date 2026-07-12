<?php

use App\Services\Ai\AiGateway;
use App\Services\Ai\Drivers\AnthropicAiDriver;
use App\Services\Ai\Drivers\NullAiDriver;
use Illuminate\Support\Facades\Http;

test('the null driver is the default and returns safe empty results', function () {
    $gateway = app(AiGateway::class);

    expect($gateway)->toBeInstanceOf(NullAiDriver::class)
        ->and($gateway->enabled())->toBeFalse()
        ->and($gateway->moderateText('anything'))->toBeNull()
        ->and($gateway->suggestTopics('anything'))->toBe([]);
});

test('the container binding respects the AI_DRIVER config', function () {
    config(['ai.driver' => 'anthropic', 'ai.anthropic.api_key' => 'sk-test']);
    app()->forgetInstance(AiGateway::class);

    expect(app(AiGateway::class))->toBeInstanceOf(AnthropicAiDriver::class);

    config(['ai.driver' => 'null']);
    app()->forgetInstance(AiGateway::class);

    expect(app(AiGateway::class))->toBeInstanceOf(NullAiDriver::class);
});

test('the anthropic driver is disabled without an api key', function () {
    $driver = new AnthropicAiDriver(['api_key' => null]);

    expect($driver->enabled())->toBeFalse()
        ->and($driver->moderateText('text'))->toBeNull()
        ->and($driver->suggestTopics('text'))->toBe([]);
});

test('the anthropic driver parses a moderation response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"flagged": true, "categories": ["spam", "scam", "made_up"], "confidence": 0.87, "summary": "Looks like a scam."}',
            ]],
        ]),
    ]);

    $driver = new AnthropicAiDriver(array_merge(config('ai.anthropic'), ['api_key' => 'sk-test']));

    $result = $driver->moderateText('buy now cheap deal');

    expect($result->flagged)->toBeTrue()
        ->and($result->categories)->toBe(['spam', 'scam']) // unknown category dropped
        ->and($result->confidence)->toBe(0.87)
        ->and($result->summary)->toBe('Looks like a scam.');
});

test('the anthropic driver parses a topic-suggestion response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"slugs": ["Small Business", "marketing", "marketing"]}',
            ]],
        ]),
    ]);

    $driver = new AnthropicAiDriver(array_merge(config('ai.anthropic'), ['api_key' => 'sk-test']));

    expect($driver->suggestTopics('growing my shop'))->toBe(['small-business', 'marketing']);
});

test('the anthropic driver swallows transport and http failures', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response('nope', 500),
    ]);

    $driver = new AnthropicAiDriver(array_merge(config('ai.anthropic'), ['api_key' => 'sk-test']));

    expect($driver->moderateText('text'))->toBeNull()
        ->and($driver->suggestTopics('text'))->toBe([]);
});
