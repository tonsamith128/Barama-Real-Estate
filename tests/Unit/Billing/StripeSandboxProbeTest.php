<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Services\Billing\StripeSandboxProbe;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class StripeSandboxProbeTest extends TestCase
{
    public function test_probe_is_disabled_by_default_and_never_calls_provider(): void
    {
        config(['billing.stripe.sandbox_probe_enabled' => false]);
        Http::fake();
        $result = app(StripeSandboxProbe::class)->check();
        $this->assertSame('disabled', $result['status']);
        $this->assertFalse($result['live_payments_allowed']);
        Http::assertNothingSent();
    }

    public function test_probe_rejects_live_credentials_without_network_activity(): void
    {
        config([
            'billing.stripe.sandbox_probe_enabled' => true,
            'billing.stripe.secret_key' => 'sk_live_forbidden_example',
        ]);
        Http::fake();
        $this->assertSame('test_credential_required', app(StripeSandboxProbe::class)->check()['status']);
        Http::assertNothingSent();
    }

    public function test_mock_test_account_response_reports_connection_not_merchant_approval(): void
    {
        config([
            'billing.stripe.sandbox_probe_enabled' => true,
            'billing.stripe.secret_key' => 'sk_test_only_for_unit_tests',
        ]);
        Http::fake(['api.stripe.com/v1/account' => Http::response([
            'object' => 'account', 'id' => 'acct_test_123456',
            'country' => 'SG', 'charges_enabled' => true,
        ])]);
        $report = app(StripeSandboxProbe::class)->check();
        $this->assertTrue($report['test_api_connection']);
        $this->assertSame('SG', $report['account_country']);
        $this->assertFalse($report['merchant_eligible']);
        $this->assertFalse($report['signed_webhooks_verified']);
        $this->assertFalse($report['live_payments_allowed']);
        $this->assertArrayNotHasKey('account_id', $report);
        Http::assertSentCount(1);
    }

    public function test_invalid_provider_payload_does_not_pass_probe(): void
    {
        config([
            'billing.stripe.sandbox_probe_enabled' => true,
            'billing.stripe.secret_key' => 'sk_test_only_for_unit_tests',
        ]);
        Http::fake(['api.stripe.com/v1/account' => Http::response([
            'object' => 'customer', 'id' => 'cus_fake', 'country' => 'KH',
        ])]);
        $report = app(StripeSandboxProbe::class)->check();
        $this->assertSame('invalid_provider_response', $report['status']);
        $this->assertFalse($report['test_api_connection']);
    }
}
