<?php

declare(strict_types=1);

use App\Http\Controllers\Developer\Dashboard\DeveloperDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Developer\Inquiries\DeveloperInquiryController;
use App\Http\Controllers\Developer\Properties\DeveloperPropertyController;

Route::middleware(['auth', 'verified'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function (): void {
        Route::get('/', DeveloperDashboardController::class)->name('dashboard');
        Route::get('/inquiries', DeveloperInquiryController::class)->name('inquiries.index');
        Route::get('/company/{company}/properties', [DeveloperPropertyController::class, 'index'])
            ->name('properties.index');
        Route::post('/company/{company}/properties', [DeveloperPropertyController::class, 'store'])
            ->middleware('throttle:15,1')->name('properties.store');
        Route::post('/company/{company}/properties/{property}/submit', [DeveloperPropertyController::class, 'submit'])
            ->middleware('throttle:10,1')->name('properties.submit');
    });

// All Developer mutations require active owner/manager company membership.
