<?php

declare(strict_types=1);

use App\Http\Controllers\Developer\Dashboard\DeveloperDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function (): void {
        Route::get('/', DeveloperDashboardController::class)->name('dashboard');
    });

// Do not enable Developer writes until permission policies and audit tests pass.
