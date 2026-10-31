<?php

declare(strict_types=1);

return [
    // Explicitly disabled, including in production, until merchant onboarding and acceptance.
    'enabled' => (bool) env('BILLING_CHECKOUT_ENABLED', false),
    // Only Stripe test credentials are permitted; never activate live payments from this module.
    // Independently gated: provider-managed recurring charges only in test mode.
    'recurring_test_enabled' => (bool) env('BILLING_RECURRING_TEST_ENABLED', false),
    'subscription_management_test_enabled' => (bool) env('BILLING_SUBSCRIPTION_MANAGEMENT_TEST_ENABLED', false),
    // Contract testing only. Not connected to checkout; no live/provider FX verification.
    'fx_sandbox_preview_enabled' => (bool) env('BILLING_FX_SANDBOX_PREVIEW_ENABLED', false),
    'fx_preview_provider' => env('BILLING_FX_PREVIEW_PROVIDER', ''),
    'stripe' => [
        'sandbox_probe_enabled' => (bool) env('BILLING_STRIPE_SANDBOX_PROBE_ENABLED', false),
        'portal_test_enabled' => (bool) env('BILLING_STRIPE_PORTAL_TEST_ENABLED', false),
        'portal_configuration' => env('BILLING_STRIPE_PORTAL_TEST_CONFIGURATION', ''),
        'secret_key' => env('BILLING_STRIPE_TEST_SECRET_KEY', ''),
        'webhook_secret' => env('BILLING_STRIPE_TEST_WEBHOOK_SECRET', ''),
        'api_url' => 'https://api.stripe.com',
    ],
];
