<?php

declare(strict_types=1);

return [
    // Explicitly disabled, including in production, until merchant onboarding and acceptance.
    'enabled' => (bool) env('BILLING_CHECKOUT_ENABLED', false),
    // Only Stripe test credentials are permitted; never activate live payments from this module.
    // Independently gated: provider-managed recurring charges only in test mode.
    'recurring_test_enabled' => (bool) env('BILLING_RECURRING_TEST_ENABLED', false),
    'stripe' => [
        'secret_key' => env('BILLING_STRIPE_TEST_SECRET_KEY', ''),
        'webhook_secret' => env('BILLING_STRIPE_TEST_WEBHOOK_SECRET', ''),
        'api_url' => 'https://api.stripe.com',
    ],
];
