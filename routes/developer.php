<?php

declare(strict_types=1);

use App\Http\Controllers\Developer\Dashboard\DeveloperDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Developer\Media\ListingGalleryController;
use App\Http\Controllers\Developer\Inquiries\DeveloperInquiryController;
use App\Http\Controllers\Developer\Properties\DeveloperPropertyController;
use App\Http\Controllers\Developer\Projects\DeveloperProjectController;
use App\Http\Controllers\Developer\Team\DeveloperTeamController;
use App\Http\Controllers\Developer\Finance\CompanyPlanController;

Route::middleware(['auth', 'verified'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function (): void {
        Route::get('/', DeveloperDashboardController::class)->name('dashboard');
        Route::get('/inquiries', DeveloperInquiryController::class)->name('inquiries.index');
        Route::patch('/inquiries/{inquiry}/respond', [DeveloperInquiryController::class, 'respond'])
            ->middleware('throttle:20,1')->name('inquiries.respond');
        Route::get('/viewings', [\App\Http\Controllers\Developer\Viewings\DeveloperViewingController::class, 'index'])->name('viewings.index');
        Route::post('/viewings/{viewing}/decision', [\App\Http\Controllers\Developer\Viewings\DeveloperViewingController::class, 'decide'])
            ->middleware('throttle:10,1')->name('viewings.decide');
        Route::post('/company/{company}/listings/{listing}/gallery', [ListingGalleryController::class, 'store'])
            ->middleware('throttle:10,1')->name('gallery.store');
        Route::get('/company/{company}/listings/{listing}/gallery/{asset}', [ListingGalleryController::class, 'preview'])
            ->name('gallery.preview');
        Route::delete('/company/{company}/listings/{listing}/gallery/{attachment}', [ListingGalleryController::class, 'destroy'])
            ->middleware('throttle:30,1')->name('gallery.destroy');
        Route::post('/company/{company}/plans/{plan}/checkout', [CompanyPlanController::class, 'checkout'])
            ->middleware('throttle:2,1')->name('finance.plans.checkout');
        Route::get('/company/{company}/plans', [CompanyPlanController::class, 'index'])->name('finance.plans.index');
        Route::post('/company/{company}/plans/{plan}/inquire', [CompanyPlanController::class, 'inquire'])
            ->middleware('throttle:5,1')->name('finance.plans.inquire');
        Route::get('/company/{company}/team', [DeveloperTeamController::class,'index'])->name('team.index');
        Route::post('/company/{company}/team', [DeveloperTeamController::class,'assign'])
            ->middleware('throttle:10,1')->name('team.assign');
        Route::patch('/company/{company}/team/{membership}/deactivate', [DeveloperTeamController::class,'deactivate'])
            ->middleware('throttle:10,1')->name('team.deactivate');
        Route::get('/company/{company}/projects', [DeveloperProjectController::class, 'index'])
            ->name('projects.index');
        Route::post('/company/{company}/projects', [DeveloperProjectController::class, 'store'])
            ->middleware('throttle:15,1')->name('projects.store');
        Route::put('/company/{company}/projects/{project}', [DeveloperProjectController::class, 'update'])
            ->middleware('throttle:15,1')->name('projects.update');
        Route::post('/company/{company}/projects/{project}/submit', [DeveloperProjectController::class, 'submit'])
            ->middleware('throttle:10,1')->name('projects.submit');
        Route::get('/company/{company}/properties/{property}/edit', [DeveloperPropertyController::class, 'edit'])
            ->name('properties.edit');
        Route::put('/company/{company}/properties/{property}', [DeveloperPropertyController::class, 'update'])
            ->middleware('throttle:15,1')->name('properties.update');
        Route::get('/company/{company}/properties', [DeveloperPropertyController::class, 'index'])
            ->name('properties.index');
        Route::post('/company/{company}/properties', [DeveloperPropertyController::class, 'store'])
            ->middleware('throttle:15,1')->name('properties.store');
        Route::post('/company/{company}/properties/{property}/submit', [DeveloperPropertyController::class, 'submit'])
            ->middleware('throttle:10,1')->name('properties.submit');
    });

// All Developer mutations require active owner/manager company membership.
