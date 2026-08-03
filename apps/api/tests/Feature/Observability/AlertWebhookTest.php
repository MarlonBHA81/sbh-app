<?php

use App\Contracts\IssueTracker;
use App\Jobs\FileIssueForAlert;
use App\Observability\Alert;
use App\Services\Observability\GithubIssueTracker;
use App\Services\Observability\NullIssueTracker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

const ALERT_URL = '/api/v1/observability/alert';
const ALERT_SECRET = 'test-alert-secret';

/**
 * POST a raw JSON body with a caller-controlled X-SBH-Signature header so the
 * bytes the controller hashes match exactly.
 */
function postAlert(array $payload, ?string $signature = null, string $header = 'HTTP_X_SBH_SIGNATURE')
{
    $raw = json_encode($payload);
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($signature !== null) {
        $server[$header] = $signature;
    }

    return test()->call('POST', ALERT_URL, [], [], [], $server, $raw);
}

function signAlert(array $payload, string $secret = ALERT_SECRET): string
{
    return hash_hmac('sha256', json_encode($payload), $secret);
}

test('a valid signature queues an issue-filing job', function () {
    config(['observability.alert_webhook_secret' => ALERT_SECRET]);
    Bus::fake();

    $payload = ['message' => 'Boom in FeedService', 'level' => 'error'];

    postAlert($payload, signAlert($payload))->assertStatus(202);

    Bus::assertDispatched(FileIssueForAlert::class);
});

test('Sentry-Hook-Signature header is also accepted', function () {
    config(['observability.alert_webhook_secret' => ALERT_SECRET]);
    Bus::fake();

    $payload = ['message' => 'Boom'];

    postAlert($payload, signAlert($payload), 'HTTP_SENTRY_HOOK_SIGNATURE')->assertStatus(202);

    Bus::assertDispatched(FileIssueForAlert::class);
});

test('a missing signature is rejected', function () {
    config(['observability.alert_webhook_secret' => ALERT_SECRET]);
    Bus::fake();

    postAlert(['message' => 'Boom'])->assertStatus(401);

    Bus::assertNotDispatched(FileIssueForAlert::class);
});

test('a wrong signature is rejected', function () {
    config(['observability.alert_webhook_secret' => ALERT_SECRET]);
    Bus::fake();

    postAlert(['message' => 'Boom'], 'deadbeef')->assertStatus(401);

    Bus::assertNotDispatched(FileIssueForAlert::class);
});

test('a blank secret fails closed even with a signature', function () {
    config(['observability.alert_webhook_secret' => '']);
    Bus::fake();

    $payload = ['message' => 'Boom'];
    // Sign with the empty secret; the controller must still reject.
    postAlert($payload, signAlert($payload, ''))->assertStatus(401);

    Bus::assertNotDispatched(FileIssueForAlert::class);
});

test('the null issue tracker is bound by default', function () {
    config(['observability.driver' => 'null']);

    expect(app(IssueTracker::class))->toBeInstanceOf(NullIssueTracker::class);
});

test('the github issue tracker is bound when selected', function () {
    config(['observability.driver' => 'github']);

    expect(app(IssueTracker::class))->toBeInstanceOf(GithubIssueTracker::class);
});

test('the same error produces a stable fingerprint (dedup key)', function () {
    $a = Alert::fromWebhook(['event' => ['title' => 'X', 'culprit' => 'FeedService@build']]);
    $b = Alert::fromWebhook(['event' => ['title' => 'X', 'culprit' => 'FeedService@build']]);
    $c = Alert::fromWebhook(['event' => ['title' => 'Y', 'culprit' => 'FeedService@build']]);

    expect($a->fingerprint)->toBe($b->fingerprint)
        ->and($a->fingerprint)->not->toBe($c->fingerprint);
});

test('github tracker opens a new issue when none exists for the fingerprint', function () {
    Http::fake([
        '*/search/issues*' => Http::response(['items' => []], 200),
        '*/issues' => Http::response(['html_url' => 'https://github.com/o/r/issues/1'], 201),
    ]);

    $tracker = new GithubIssueTracker([
        'token' => 't', 'repo' => 'o/r', 'labels' => ['sentry'],
        'api_url' => 'https://api.github.com', 'timeout' => 15,
    ]);

    $url = $tracker->report(Alert::fromWebhook(['message' => 'Boom']));

    expect($url)->toBe('https://github.com/o/r/issues/1');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/repos/o/r/issues') && $r->method() === 'POST');
});

test('github tracker comments on the existing issue instead of duplicating', function () {
    Http::fake([
        '*/search/issues*' => Http::response([
            'items' => [['number' => 7, 'html_url' => 'https://github.com/o/r/issues/7']],
        ], 200),
        '*/issues/7/comments' => Http::response([], 201),
    ]);

    $tracker = new GithubIssueTracker([
        'token' => 't', 'repo' => 'o/r', 'labels' => ['sentry'],
        'api_url' => 'https://api.github.com', 'timeout' => 15,
    ]);

    $url = $tracker->report(Alert::fromWebhook(['message' => 'Boom']));

    expect($url)->toBe('https://github.com/o/r/issues/7');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/issues/7/comments') && $r->method() === 'POST');
    // No new issue created.
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/repos/o/r/issues') && $r->method() === 'POST');
});
