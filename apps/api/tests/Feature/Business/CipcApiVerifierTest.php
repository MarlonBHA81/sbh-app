<?php

use App\Contracts\CipcVerifier;
use App\Services\Business\CipcApiVerifier;
use App\Services\Business\CipcResult;
use Illuminate\Support\Facades\Http;

function cipcApiVerifier(array $overrides = []): CipcApiVerifier
{
    return new CipcApiVerifier(array_merge([
        'base_url' => 'https://apim.cipc.co.za/companies-api/v1',
        'subscription_key' => 'sub-key-123',
        'token' => 'static-bearer',
        'timeout' => 15,
    ], $overrides));
}

test('it verifies an enterprise and maps the CIPC response', function () {
    Http::fake([
        'apim.cipc.co.za/*' => Http::response([
            'Enterprise' => [[
                'enterprise_number' => '2020/939681/07',
                'enterprise_name' => 'ACME TRADING (PTY) LTD',
                'enterprise_status_description' => 'In Business',
            ]],
        ], 200),
    ]);

    $result = cipcApiVerifier()->lookup('2020/939681/07');

    expect($result->isVerified())->toBeTrue()
        ->and($result->registeredName)->toBe('ACME TRADING (PTY) LTD')
        ->and($result->companyStatus)->toBe('In Business');
});

test('it posts the enterprise number with the subscription key and bearer token', function () {
    Http::fake(['apim.cipc.co.za/*' => Http::response(['Enterprise' => [['enterprise_name' => 'X']]], 200)]);

    cipcApiVerifier()->lookup('2020/939681/07');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://apim.cipc.co.za/companies-api/v1/information'
            && $request->method() === 'POST'
            && $request['enterprise_number'] === '2020/939681/07'
            && $request->header('Ocp-Apim-Subscription-Key')[0] === 'sub-key-123'
            && $request->header('Authorization')[0] === 'Bearer static-bearer';
    });
});

test('an empty Enterprise list is treated as not found', function () {
    Http::fake(['apim.cipc.co.za/*' => Http::response(['Enterprise' => []], 200)]);

    expect(cipcApiVerifier()->lookup('2020/939681/07')->status)->toBe(CipcResult::NOT_FOUND);
});

test('a single (non-list) Enterprise record is still parsed', function () {
    Http::fake(['apim.cipc.co.za/*' => Http::response([
        'Enterprise' => ['enterprise_name' => 'SOLO CO', 'enterprise_status_description' => 'In Business'],
    ], 200)]);

    $result = cipcApiVerifier()->lookup('2020/939681/07');

    expect($result->isVerified())->toBeTrue()
        ->and($result->registeredName)->toBe('SOLO CO');
});

test('a 401 from CIPC is unavailable (hard gate blocks, not a false negative)', function () {
    Http::fake(['apim.cipc.co.za/*' => Http::response([
        'statusCode' => 401, 'message' => 'Unauthorized. Access token is missing or invalid.',
    ], 401)]);

    $result = cipcApiVerifier()->lookup('2020/939681/07');

    expect($result->isUnavailable())->toBeTrue()
        ->and($result->reason)->toBe('unauthorized');
});

test('a network failure yields unavailable and never throws', function () {
    Http::fake(fn () => throw new RuntimeException('connection reset'));

    expect(cipcApiVerifier()->lookup('2020/939681/07')->isUnavailable())->toBeTrue();
});

test('it fetches an OAuth client-credentials token when no static token is set', function () {
    Http::fake([
        'login.cipc.example/token' => Http::response(['access_token' => 'fetched-tok', 'expires_in' => 3600], 200),
        'apim.cipc.co.za/*' => Http::response(['Enterprise' => [['enterprise_name' => 'TOKENED CO']]], 200),
    ]);

    $verifier = cipcApiVerifier([
        'token' => null,
        'token_url' => 'https://login.cipc.example/token',
        'client_id' => 'cid',
        'client_secret' => 'secret',
    ]);

    expect($verifier->lookup('2020/939681/07')->isVerified())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'login.cipc.example/token')
        && $request['grant_type'] === 'client_credentials'
        && $request['client_id'] === 'cid');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/information')
        && $request->header('Authorization')[0] === 'Bearer fetched-tok');
});

test('the container resolves the cipc driver when configured', function () {
    config()->set('services.cipc.enabled', true);
    config()->set('services.cipc.driver', 'cipc');

    expect(app(CipcVerifier::class))->toBeInstanceOf(CipcApiVerifier::class);
});
