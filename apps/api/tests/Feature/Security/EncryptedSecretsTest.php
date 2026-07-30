<?php

use App\Models\Setting;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;

test('setting values are encrypted at rest but read back in clear', function () {
    Setting::set('integrations.ai.anthropic_api_key', 'sk-ant-supersecret');

    $raw = DB::table('settings')
        ->where('key', 'integrations.ai.anthropic_api_key')
        ->value('value');

    // On disk it is ciphertext, not the plaintext key…
    expect($raw)->not->toContain('sk-ant-supersecret');
    // …but the model decrypts it transparently.
    expect(Setting::get('integrations.ai.anthropic_api_key'))->toBe('sk-ant-supersecret');
});

test('settings tolerate legacy plaintext rows written before encryption', function () {
    // A row as it existed before the encrypted cast: plaintext JSON.
    DB::table('settings')->insert([
        'key' => 'legacy.flag',
        'value' => json_encode('legacy-value'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Setting::forget('legacy.flag');

    expect(Setting::get('legacy.flag'))->toBe('legacy-value');

    // Re-writing it upgrades to ciphertext.
    Setting::set('legacy.flag', 'new-value');
    $raw = DB::table('settings')->where('key', 'legacy.flag')->value('value');
    expect($raw)->not->toContain('new-value')
        ->and(Setting::get('legacy.flag'))->toBe('new-value');
});

test('webhook endpoint secret and header value are encrypted at rest', function () {
    $endpoint = WebhookEndpoint::create([
        'name' => 'CRM',
        'url' => 'https://example.com/hook',
        'format' => 'generic',
        'secret' => 'hmac-shared-secret',
        'header_name' => 'Authorization',
        'header_value' => 'Bearer top-secret-token',
        'events' => [],
        'is_active' => true,
    ]);

    $row = DB::table('webhook_endpoints')->where('id', $endpoint->id)->first();

    expect($row->secret)->not->toContain('hmac-shared-secret')
        ->and($row->header_value)->not->toContain('top-secret-token')
        ->and($endpoint->fresh()->secret)->toBe('hmac-shared-secret')
        ->and($endpoint->fresh()->header_value)->toBe('Bearer top-secret-token');
});
