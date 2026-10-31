<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\Billing\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Stripe verifies raw request body HMAC; CSRF exemption only for this exact route.
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:90,1')->name('webhooks.stripe');
