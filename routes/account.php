<?php

declare(strict_types=1);

use App\Http\Controllers\Account\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});

// The rest of the Account workspace is not exposed until migrated and tested.
