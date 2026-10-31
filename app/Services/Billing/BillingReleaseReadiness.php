<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BillingReleaseReadiness
{
    /**
     * Read-only diagnostics. External approval can never be inferred from
     * installed code, environment flags or a successful local test.
     *
     * @return array<string,mixed>
     */
    public function report(): array
    {
        $checks = [];
        $put = static function (string $key, bool $ok, string $detail) use (&$checks): void {
            $checks[$key] = ['status' => $ok ? 'pass' : 'fail', 'detail' => $detail];
        };

        $put('php_version', PHP_VERSION_ID >= 80300, 'PHP 8.3 or newer');
        foreach (['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json', 'curl', 'fileinfo'] as $extension) {
            $put('php_'.$extension, extension_loaded($extension), 'PHP extension required for MySQL/shared-hosting billing');
        }
        $put('https_origin', parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https',
            'HTTPS application URL; reverse proxy and certificate must be tested independently');
        $put('debug_disabled', !config('app.debug'), 'Application debug must be disabled');
        $put('no_public_env', !is_file(public_path('.env')), 'Public document root must not contain .env');
        $put('no_public_vendor', !is_dir(public_path('vendor')), 'PHP dependency sources must not be in public document root');

        $database = (string) config('database.default');
        $put('mysql_driver', $database === 'mysql', 'Production acceptance requires MySQL 8');
        try {
            // SELECT-only connectivity. Never migrate, repair or write data here.
            DB::connection()->select('SELECT 1');
            $put('database_connection', true, 'Read-only SELECT succeeded');
        } catch (Throwable) {
            $put('database_connection', false, 'Read-only SELECT unavailable');
        }

        $required = [
            'billing_orders', 'billing_invoices', 'billing_payments',
            'billing_refunds', 'billing_webhook_events', 'billing_disputes',
            'billing_tax_reviews', 'billing_renewals', 'company_subscriptions',
            'company_billing_profiles',
        ];
        foreach ($required as $table) {
            try {
                $ok = Schema::hasTable($table);
            } catch (Throwable) {
                $ok = false;
            }
            $put('table_'.$table, $ok, 'Forward migration for '.$table);
        }

        $checkoutEnabled = (bool) config('billing.enabled');
        $secretKey = (string) config('billing.stripe.secret_key');
        $webhookKey = (string) config('billing.stripe.webhook_secret');
        $put('live_charges_blocked', !$checkoutEnabled
            || (str_starts_with($secretKey, 'sk_test_') && str_starts_with($webhookKey, 'whsec_')),
            'If sandbox checkout is enabled, both keys must be Stripe test-only');
        $put('safe_default_checkout', !$checkoutEnabled,
            'Default release installation must have checkout disabled');
        $put('safe_default_recurring', !config('billing.recurring_test_enabled'),
            'Default release installation must have recurring test mode disabled');
        $put('safe_default_management', !config('billing.subscription_management_test_enabled'),
            'Default release installation must have test subscription management disabled');

        $external = [
            'merchant_eligibility' => 'Written onboarding eligibility for the actual legal entity and its provider',
            'provider_sandbox' => 'Genuine provider-generated checkout, renewal, failure, dispute and refund events',
            'tax_and_receipts' => 'Professional jurisdiction, invoice schema, tax registration and e-invoicing review',
            'fx_and_local_currency' => 'Provider quote, conversion/rounding policy and ledger reconciliation; not implemented',
            'localization_review' => 'Native-speaker, accessibility and regional legal-content review',
            'mysql_concurrency' => 'MySQL 8 multi-worker duplicate-event and database locking acceptance',
            'cpanel_install_upgrade' => 'Independent fresh-install and forward-upgrade with backup restore on cPanel',
            'aapanel_install_upgrade' => 'Independent fresh-install and forward-upgrade with backup restore on aaPanel',
            'security_release_review' => 'Independent security test, provider-version compatibility and customer ZIP inspection',
        ];

        return [
            'scope' => 'read_only_local_preflight',
            'live_ready' => false,
            'local_checks_pass' => !in_array('fail', array_column($checks, 'status'), true),
            'local_checks' => $checks,
            'external_gates' => array_map(
                static fn (string $reason): array => ['status' => 'not_verified', 'required_evidence' => $reason],
                $external,
            ),
            'message' => 'Local checks never authorize live payments; external release gates remain unverified.',
        ];
    }
}
