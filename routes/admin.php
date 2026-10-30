<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\DemoImport\DemoImportController;
use Illuminate\Support\Facades\Route;

// Every action independently authorizes the verified system administrator.
Route::middleware(['auth', 'verified', 'throttle:30,1'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/system/demo-importer', [DemoImportController::class, 'index'])->name('demo.index');
        Route::post('/system/demo-importer/preview', [DemoImportController::class, 'preview'])
            ->middleware('throttle:5,1')->name('demo.preview');
        Route::post('/system/demo-importer/import', [DemoImportController::class, 'install'])
            ->middleware('throttle:2,1')->name('demo.install');
    });
