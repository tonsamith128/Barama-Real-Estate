<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Services\Billing\FxQuoteContract;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

final class FxQuoteContractTest extends TestCase
{
    private function quote(array $overrides = []): array
    {
        return array_replace([
            'provider' => 'qa_mock_provider',
            'reference' => 'test_quote_123456',
            'source_currency' => 'USD',
            'target_currency' => 'JPY',
            'issued_at' => time() - 30,
            'expires_at' => time() + 300,
            'minor_units_numerator' => 150,
            'minor_units_denominator' => 100,
        ], $overrides);
    }

    public function test_fx_contract_is_disabled_by_default(): void
    {
        config(['billing.fx_sandbox_preview_enabled' => false]);
        $this->expectException(DomainException::class);
        app(FxQuoteContract::class)->preview($this->quote(), 1999);
    }

    public function test_zero_decimal_currency_uses_half_up_integer_rounding_without_charging(): void
    {
        config(['billing.fx_sandbox_preview_enabled' => true, 'billing.fx_preview_provider' => 'qa_mock_provider']);
        $value = app(FxQuoteContract::class)->preview($this->quote(), 1999);
        $this->assertSame(2999, $value['target_minor_units']); // 2998.5 JPY rounds to 2999
        $this->assertSame(0, $value['target_exponent']);
        $this->assertFalse($value['checkout_allowed']);
        $this->assertFalse($value['provider_live_accepted']);
    }

    public function test_two_decimal_currency_is_also_integer_bounded(): void
    {
        config(['billing.fx_sandbox_preview_enabled' => true, 'billing.fx_preview_provider' => 'qa_mock_provider']);
        $value = app(FxQuoteContract::class)->preview($this->quote([
            'target_currency' => 'EUR',
            'minor_units_numerator' => 95, 'minor_units_denominator' => 100,
        ]), 1999);
        $this->assertSame(1899, $value['target_minor_units']); // 1899.05 => 1899 minor units
        $this->assertSame(2, $value['target_exponent']);
    }

    public function test_invalid_provider_expired_rate_and_invalid_currency_fail_closed(): void
    {
        config(['billing.fx_sandbox_preview_enabled' => true, 'billing.fx_preview_provider' => 'qa_mock_provider']);
        foreach ([
            ['provider' => 'untrusted_api'],
            ['expires_at' => time() - 1],
            ['target_currency' => 'BTC'],
            ['source_currency' => 'KHR'],
            ['minor_units_denominator' => 0],
            ['issued_at' => time() - 1000],
        ] as $changes) {
            try {
                app(FxQuoteContract::class)->preview($this->quote($changes), 1000);
                $this->fail('Bad FX quote was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
