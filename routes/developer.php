<?php

declare(strict_types=1);

use App\Http\Controllers\Developer\Dashboard\DeveloperDashboardController;
use App\Http\Controllers\Developer\Inquiries\DeveloperInquiryThreadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Developer\Media\ListingGalleryController;
use App\Http\Controllers\Developer\Media\ProjectGalleryController;
use App\Http\Controllers\Developer\Catalog\CatalogRevisionController;
use App\Http\Controllers\Developer\Inquiries\DeveloperInquiryController;
use App\Http\Controllers\Developer\Properties\DeveloperPropertyController;
use App\Http\Controllers\Developer\Projects\DeveloperProjectController;
use App\Http\Controllers\Developer\Projects\ProjectMilestoneController;
use App\Http\Controllers\Developer\Properties\ListingOfferController;
use App\Http\Controllers\Developer\Catalog\CatalogAmenityController;
use App\Http\Controllers\Developer\Team\DeveloperTeamController;
use App\Http\Controllers\Developer\Companies\CompanyApplicationController;

Route::middleware(['auth', 'verified'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function (): void {
        Route::get('/', DeveloperDashboardController::class)->name('dashboard');
        Route::post('/company-applications', [CompanyApplicationController::class, 'store'])
            ->middleware('throttle:5,1')->name('companies.apply');
        Route::patch('/company-applications/{company}', [CompanyApplicationController::class, 'update'])
            ->middleware('throttle:10,1')->name('companies.application.update');
        Route::post('/company-applications/{company}/submit', [CompanyApplicationController::class, 'submit'])
            ->middleware('throttle:5,1')->name('companies.application.submit');
        Route::get('/inquiries', DeveloperInquiryController::class)->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [DeveloperInquiryThreadController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/messages', [DeveloperInquiryThreadController::class, 'store'])
            ->middleware('throttle:8,1')->name('inquiries.messages.store');
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
        Route::post('/company/{company}/listings/{listing}/gallery/{attachment}/order', [ListingGalleryController::class, 'order'])
            ->middleware('throttle:30,1')->name('gallery.order');

        Route::post('/company/{company}/projects/{project}/gallery', [ProjectGalleryController::class, 'store'])
            ->middleware('throttle:10,1')->name('projects.gallery.store');
        Route::get('/company/{company}/projects/{project}/gallery/{asset}', [ProjectGalleryController::class, 'preview'])
            ->name('projects.gallery.preview');
        Route::delete('/company/{company}/projects/{project}/gallery/{attachment}', [ProjectGalleryController::class, 'destroy'])
            ->middleware('throttle:30,1')->name('projects.gallery.destroy');
        Route::post('/company/{company}/projects/{project}/gallery/{attachment}/order', [ProjectGalleryController::class, 'order'])
            ->middleware('throttle:30,1')->name('projects.gallery.order');
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
        Route::put('/company/{company}/projects/{project}/amenities', [CatalogAmenityController::class, 'project'])
            ->middleware('throttle:15,1')->name('projects.amenities.update');
        Route::post('/company/{company}/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])
            ->middleware('throttle:15,1')->name('projects.milestones.store');
        Route::put('/company/{company}/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])
            ->middleware('throttle:15,1')->name('projects.milestones.update');
        Route::delete('/company/{company}/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])
            ->middleware('throttle:15,1')->name('projects.milestones.destroy');
        Route::post('/company/{company}/projects/{project}/milestones/{milestone}/move', [ProjectMilestoneController::class, 'move'])
            ->middleware('throttle:15,1')->name('projects.milestones.move');
        Route::get('/company/{company}/projects/{project}/history', [CatalogRevisionController::class, 'project'])
            ->name('projects.history');
        Route::get('/company/{company}/properties/{property}/history', [CatalogRevisionController::class, 'property'])
            ->name('properties.history');
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
        Route::put('/company/{company}/properties/{property}/amenities', [CatalogAmenityController::class, 'property'])
            ->middleware('throttle:15,1')->name('properties.amenities.update');
        Route::post('/company/{company}/properties/{property}/offers', [ListingOfferController::class, 'store'])
            ->middleware('throttle:10,1')->name('properties.offers.store');
        Route::put('/company/{company}/properties/{property}/offers/{offer}', [ListingOfferController::class, 'update'])
            ->middleware('throttle:15,1')->name('properties.offers.update');
        Route::post('/company/{company}/properties/{property}/offers/{offer}/submit', [ListingOfferController::class, 'submit'])
            ->middleware('throttle:10,1')->name('properties.offers.submit');
    });

// All Developer mutations require active owner/manager company membership.
