<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_outside_production_only_universal_checks_run(): void
    {
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'app.url' => 'http://localhost',
        ]);

        $this->artisan('ops:preflight')
            ->expectsOutputToContain('not production')
            ->assertSuccessful();
    }

    public function test_production_misconfiguration_fails_without_printing_secrets(): void
    {
        $key = 'base64:'.base64_encode(str_repeat('k', 32));
        $xentriKey = 'xentri-super-secret-key';

        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.key' => $key,
            'app.url' => 'http://localhost',
            'app.trusted_hosts' => [],
            'session.driver' => 'array',
            'session.encrypt' => false,
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => 'none',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'payments.sandbox_enabled' => true,
            'payments.webhook_enabled' => true,
            'payments.webhook_secret' => 'short',
            'payments.xentripay.enabled' => true,
            'payments.xentripay.base_url' => 'http://merchant.test.xentripay.com',
            'payments.xentripay.api_key' => $xentriKey,
            'monitoring.enabled' => false,
            'monitoring.alert_webhook' => 'http://alerts.example.test/hook',
        ]);

        $this->artisan('ops:preflight')
            ->expectsOutputToContain('FAIL')
            ->doesntExpectOutputToContain(base64_encode(str_repeat('k', 32)))
            ->doesntExpectOutputToContain($xentriKey)
            ->assertFailed();
    }

    public function test_production_configuration_passes_the_required_checks(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://shop.example.test',
            'app.trusted_hosts' => ['shop.example.test'],
            'app.trusted_proxies' => [],
            'app.trust_proxy_headers' => true,
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'payments.sandbox_enabled' => false,
            'payments.webhook_enabled' => true,
            'payments.webhook_secret' => 'a-sufficiently-long-webhook-secret',
            'payments.xentripay.enabled' => true,
            'payments.xentripay.base_url' => 'https://merchant.xentripay.com',
            'payments.xentripay.api_key' => 'live-key-value',
            'payments.xentripay.webhook_secret' => 'live-webhook-secret',
            'monitoring.enabled' => true,
            'monitoring.alert_webhook' => 'https://hooks.example.test/alerts',
            'logging.default' => 'stderr',
        ]);

        $this->artisan('ops:preflight')
            ->expectsOutputToContain('Preflight passed.')
            ->assertSuccessful();
    }

    public function test_strict_mode_promotes_warnings_to_failures(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://shop.example.test',
            'app.trusted_hosts' => ['shop.example.test'],
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'payments.sandbox_enabled' => false,
            'payments.webhook_enabled' => false,
            'payments.webhook_secret' => null,
            'payments.xentripay.enabled' => false,
            'monitoring.enabled' => true,
            'monitoring.alert_webhook' => '',
        ]);

        $this->artisan('ops:preflight')->assertSuccessful();
        $this->artisan('ops:preflight', ['--strict' => true])
            ->expectsOutputToContain('Preflight failed.')
            ->assertFailed();
    }

    public function test_reports_unenrolled_administrators(): void
    {
        $pending = User::factory()->create(['role' => 'admin']);
        $pending->forceFill(['two_factor_confirmed_at' => null])->save();
        $enrolled = User::factory()->create(['role' => 'admin']);
        $enrolled->forceFill(['two_factor_confirmed_at' => now()])->save();

        config(['app.env' => 'production']);

        $this->artisan('ops:preflight')
            ->expectsOutputToContain('1 of 2 administrators have not completed enrollment')
            ->assertFailed();
    }

    public function test_recovery_like_values_are_never_echoed(): void
    {
        $secret = 'whsec_'.Str::random(40);
        config([
            'app.env' => 'production',
            'app.url' => 'https://shop.example.test',
            'payments.webhook_enabled' => true,
            'payments.webhook_secret' => $secret,
        ]);

        $this->artisan('ops:preflight')
            ->doesntExpectOutputToContain($secret)
            ->run();
    }
}
