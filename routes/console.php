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
