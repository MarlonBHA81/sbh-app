<?php

use App\Filament\Pages\Integrations;
use App\Models\Setting;
use Livewire\Livewire;

test('the integrations page renders for a super admin', function () {
    $admin = superAdminWithProfile();

    $this->actingAs($admin)
        ->get('/admin/integrations')
        ->assertSuccessful()
        ->assertSee('AI provider')
        ->assertSee('Anthropic API key')
        ->assertSee('OpenAI API key');
});

test('the integrations page is forbidden for a regular admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/integrations')->assertForbidden();
});

test('saving stores the model and per-provider keys independently', function () {
    $admin = superAdminWithProfile();

    Livewire::actingAs($admin)
        ->test(Integrations::class)
        ->fillForm([
            'ai_driver' => 'anthropic',
            'ai_model' => 'claude-sonnet-5',
            'ai_anthropic_api_key' => 'sk-ant-xyz',
            'ai_openai_api_key' => 'sk-openai-xyz',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('integrations.ai.model'))->toBe('claude-sonnet-5')
        ->and(Setting::get('integrations.ai.anthropic_api_key'))->toBe('sk-ant-xyz')
        ->and(Setting::get('integrations.ai.openai_api_key'))->toBe('sk-openai-xyz');
});

test('saving stores the PayFast payment settings', function () {
    $admin = superAdminWithProfile();

    Livewire::actingAs($admin)
        ->test(Integrations::class)
        ->fillForm([
            'payments_driver' => 'payfast',
            'payfast_merchant_id' => '10000100',
            'payfast_merchant_key' => '46f0cd694581a',
            'payments_platform_fee_percent' => '12',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('integrations.payments.driver'))->toBe('payfast')
        ->and(Setting::get('integrations.payments.payfast.merchant_id'))->toBe('10000100')
        ->and(Setting::get('integrations.payments.platform_fee_percent'))->toBe('12');
});

test('the model options list every available model for the driver', function () {
    expect(Integrations::modelsFor('anthropic'))
        ->toHaveKeys([
            'claude-haiku-4-5-20251001',
            'claude-sonnet-5',
            'claude-opus-4-8',
            'claude-fable-5',
        ]);

    expect(Integrations::modelsFor('openai'))
        ->toHaveKeys(['gpt-4o-mini', 'gpt-4o']);

    // Unknown/disabled driver falls back to the Anthropic list (never empty).
    expect(Integrations::modelsFor('null'))->toBe(Integrations::modelsFor('anthropic'));
    expect(array_key_first(Integrations::modelsFor('anthropic')))->toBe('claude-haiku-4-5-20251001');
});
