<?php

declare(strict_types=1);

namespace App\Services\Billing;

use DomainException;
use InvalidArgumentException;

/**
 * Isolated sandbox quote arithmetic. This class neither obtains provider rates
 * nor authorizes payment, settlement, tax or foreign exchange execution.
 */
final class FxQuoteContract
{
    private const ZERO_DECIMAL = ['JPY', 'KRW'];

    /**
     * Validate a hypothetical provider-supplied minor-unit quote.
     * Integers avoid floating point price drift. Only a separately verified
     * gateway adapter may ever classify a rate as provider-verified.
     *
     * @param array<string, mixed> $quote
     */
    public function preview(array $quote, int $usdCents): array
    {
        if (!config('billing.fx_sandbox_preview_enabled')) {
            throw new DomainException('FX preview is disabled.');
        }
        if ($usdCents < 1 || $usdCents > 99999999) {
            throw new InvalidArgumentException('USD amount is outside the supported preview range.');
        }
        $target = $quote['target_currency'] ?? null;
        if (($quote['source_currency'] ?? null) !== 'USD'
            || !is_string($target)
            || !in_array($target, GlobalBillingPreferences::CURRENCIES, true)
            || $target === 'USD') {
            throw new InvalidArgumentException('Currency contract is invalid.');
        }
        $provider = $quote['provider'] ?? null;
        if (!is_string($provider) || !preg_match('/^[A-Za-z0-9_-]{3,64}$/D', $provider)
            || !hash_equals((string) config('billing.fx_preview_provider'), $provider)) {
            throw new InvalidArgumentException('Configured sandbox provider does not match.');
        }
        $reference = $quote['reference'] ?? null;
        if (!is_string($reference) || !preg_match('/^[A-Za-z0-9_-]{8,90}$/D', $reference)) {
            throw new InvalidArgumentException('Quote reference is invalid.');
        }
        $issued = $quote['issued_at'] ?? null;
        $expires = $quote['expires_at'] ?? null;
        if (!is_int($issued) || !is_int($expires) || $issued > time() + 30
            || $issued < time() - 900 || $expires <= time()
            || $expires <= $issued || $expires > $issued + 900) {
            throw new InvalidArgumentException('Quote is expired, future-dated or too long-lived.');
        }
        $numerator = $quote['minor_units_numerator'] ?? null;
        $denominator = $quote['minor_units_denominator'] ?? null;
        if (!is_int($numerator) || !is_int($denominator)
            || $numerator < 1 || $denominator < 1
            || $numerator > 1000000000 || $denominator > 1000000000) {
            throw new InvalidArgumentException('Quote has invalid integer conversion units.');
        }
        // Bounded multiplication: values are always within signed int64.
        $numeratorTotal = $usdCents * $numerator;
        $targetMinor = intdiv($numeratorTotal, $denominator);
        $remainder = $numeratorTotal % $denominator;
        if ($remainder >= intdiv($denominator, 2) + ($denominator % 2)) {
            $targetMinor++;
        }
        if ($targetMinor < 1 || $targetMinor > 999999999) {
            throw new InvalidArgumentException('Converted amount exceeds preview bounds.');
        }

        return [
            'source_currency' => 'USD',
            'source_minor_units' => $usdCents,
            'target_currency' => $target,
            'target_minor_units' => $targetMinor,
            'target_exponent' => in_array($target, self::ZERO_DECIMAL, true) ? 0 : 2,
            'reference' => $reference,
            'expires_at' => $expires,
            'sandbox_only' => true,
            'provider_live_accepted' => false,
            'checkout_allowed' => false,
        ];
    }
}
