<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\DemoImport\DemoImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Media\MediaLibraryController;
use App\Http\Controllers\Admin\Licensing\LicenseController;
use App\Http\Controllers\Admin\Inquiries\AdminInquiryController;
use App\Http\Controllers\Admin\Catalog\CatalogModerationController;

// Every action independently authorizes the verified system administrator.
Route::middleware(['auth', 'verified', 'throttle:30,1'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/license', [LicenseController::class, 'index'])->name('license.index');
        Route::post('/license/activate', [LicenseController::class, 'activate'])->middleware('throttle:3,1')->name('license.activate');
        Route::get('/inquiries', AdminInquiryController::class)->name('inquiries.index');
        Route::get('/media', MediaLibraryController::class)->name('media.index');
        Route::get('/catalog', [CatalogModerationController::class, 'index'])->name('catalog.index');
        Route::post('/catalog/companies', [CatalogModerationController::class, 'createCompany'])
            ->middleware('throttle:10,1')->name('catalog.companies.create');
        Route::post('/catalog/listings/{listing}/approve', [CatalogModerationController::class, 'approve'])
            ->middleware('throttle:20,1')->name('catalog.approve');
        Route::get('/system/demo-importer', [DemoImportController::class, 'index'])->name('demo.index');
        Route::post('/system/demo-importer/preview', [DemoImportController::class, 'preview'])
            ->middleware('throttle:5,1')->name('demo.preview');
        Route::post('/system/demo-importer/import', [DemoImportController::class, 'install'])
            ->middleware('throttle:2,1')->name('demo.install');
    });
