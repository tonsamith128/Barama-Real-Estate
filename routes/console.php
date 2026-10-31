<?php

declare(strict_types=1);

use App\Services\Billing\BillingReconciliation;
use Illuminate\Support\Facades\Artisan;

// Read-only; safe for an operator to run on a staging database.
// Never executes a charge, alters invoices, or changes entitlements.
Artisan::command('billing:audit {--json : Print JSON only}', function (BillingReconciliation $reconciliation): void {
    $summary = $reconciliation->summary();
    $output = json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

    $this->line($output);
})->purpose('Read-only billing reconciliation status');


// Read-only readiness report. --strict is intentionally nonzero while any
// externally verified acceptance requirement is outstanding.
Artisan::command('billing:release-check {--json : Print JSON only} {--strict : Fail until all release gates are approved}', function (\App\Services\Billing\BillingReleaseReadiness $readiness): int {
    $report = $readiness->report();
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $this->option('strict') && !$report['live_ready'] ? 2 : 0;
})->purpose('Check local billing prerequisites; never authorizes live payments');


// Explicit read-only connectivity check for an operator-supplied sk_test_ key.
// Never validates payments or changes any financial ledger.
Artisan::command('billing:stripe-sandbox-probe', function (\App\Services\Billing\StripeSandboxProbe $probe): int {
    $result = $probe->check();
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $result['test_api_connection'] ? 0 : 2;
})->purpose('Check Stripe test API reachability without approving merchant or live payments');
