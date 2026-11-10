<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\Appearance\AppearanceController;
use App\Http\Controllers\Admin\Appearance\HomePageBuilderController;
use App\Http\Controllers\Admin\DemoImport\DemoImportController;
use App\Http\Controllers\Admin\Inquiries\AdminInquiryThreadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Users\UserDirectoryController;
use App\Http\Controllers\Admin\Contacts\ContactInboxController;
use App\Http\Controllers\Admin\Policy\LegalPolicyController;
use App\Http\Controllers\Admin\Media\MediaLibraryController;
use App\Http\Controllers\Admin\Media\CompanyMediaController;
use App\Http\Controllers\Admin\Licensing\LicenseController;
use App\Http\Controllers\Admin\Inquiries\AdminInquiryController;
use App\Http\Controllers\Admin\Catalog\CatalogModerationController;
use App\Http\Controllers\Admin\Geography\GeographyController;

// Every action independently authorizes the verified system administrator.
Route::middleware(['auth', 'verified', 'throttle:30,1'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/appearance', [AppearanceController::class,'index'])->name('appearance.index');
        Route::post('/appearance/draft', [AppearanceController::class,'save'])
            ->middleware('throttle:15,1')->name('appearance.save');
        Route::post('/appearance/publish', [AppearanceController::class,'publish'])
            ->middleware('throttle:5,1')->name('appearance.publish');
        Route::post('/appearance/reset', [AppearanceController::class,'resetDraft'])
            ->middleware('throttle:5,1')->name('appearance.reset');
        Route::get('/appearance/homepage', [HomePageBuilderController::class, 'index'])->name('homepage.index');
        Route::post('/appearance/homepage/hero-image', [HomePageBuilderController::class, 'changeHeroImage'])
            ->middleware('throttle:15,1')->name('homepage.hero-image.update');
        Route::post('/appearance/homepage/sections', [HomePageBuilderController::class, 'createSection'])
            ->middleware('throttle:15,1')->name('homepage.sections.store');
        Route::patch('/appearance/homepage/sections/{section}', [HomePageBuilderController::class, 'updateSection'])
            ->middleware('throttle:15,1')->name('homepage.sections.update');
        Route::post('/appearance/homepage/sections/{section}/move', [HomePageBuilderController::class, 'moveSection'])
            ->middleware('throttle:20,1')->name('homepage.sections.move');
        Route::delete('/appearance/homepage/sections/{section}', [HomePageBuilderController::class, 'removeSection'])
            ->middleware('throttle:15,1')->name('homepage.sections.remove');
        Route::post('/appearance/homepage/navigation', [HomePageBuilderController::class, 'addLink'])
            ->middleware('throttle:15,1')->name('homepage.links.store');
        Route::post('/appearance/homepage/navigation/{link}/move', [HomePageBuilderController::class, 'moveLink'])
            ->middleware('throttle:20,1')->name('homepage.links.move');
        Route::delete('/appearance/homepage/navigation/{link}', [HomePageBuilderController::class, 'removeLink'])
            ->middleware('throttle:15,1')->name('homepage.links.remove');
        Route::get('/users', UserDirectoryController::class)->name('users.index');
        Route::get('/viewings', [\App\Http\Controllers\Admin\Viewings\AdminViewingController::class, 'index'])->name('viewings.index');
        Route::post('/viewings/{viewing}/decision', [\App\Http\Controllers\Admin\Viewings\AdminViewingController::class, 'decide'])
            ->middleware('throttle:10,1')->name('viewings.decide');
        Route::get('/contacts', [ContactInboxController::class, 'index'])->name('contacts.index');
        Route::patch('/contacts/{message}/read', [ContactInboxController::class, 'read'])->name('contacts.read');
        Route::get('/policies', [LegalPolicyController::class, 'index'])->name('policies.index');
        Route::get('/policies/create', [LegalPolicyController::class, 'create'])->name('policies.create');
        Route::post('/policies', [LegalPolicyController::class, 'store'])->name('policies.store');
        Route::get('/policies/{policy}/edit', [LegalPolicyController::class, 'edit'])->name('policies.edit');
        Route::put('/policies/{policy}', [LegalPolicyController::class, 'update'])->name('policies.update');
        Route::post('/policies/{policy}/publish', [LegalPolicyController::class, 'publish'])->name('policies.publish');
        Route::post('/policies/{policy}/unpublish', [LegalPolicyController::class, 'unpublish'])->name('policies.unpublish');
        Route::get('/license', [LicenseController::class, 'index'])->name('license.index');
        Route::post('/license/activate', [LicenseController::class, 'activate'])->middleware('throttle:3,1')->name('license.activate');
        Route::post('/license/revalidate', [LicenseController::class, 'revalidate'])
            ->middleware('throttle:2,1')->name('license.revalidate');
        Route::get('/inquiries', AdminInquiryController::class)->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [AdminInquiryThreadController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/messages', [AdminInquiryThreadController::class, 'store'])
            ->middleware('throttle:8,1')->name('inquiries.messages.store');
        Route::patch('/inquiries/{inquiry}/respond', [AdminInquiryController::class, 'respond'])
            ->middleware('throttle:20,1')->name('inquiries.respond');
        Route::get('/company-media', [CompanyMediaController::class, 'index'])->name('company-media.index');
        Route::post('/company-media/capabilities', [CompanyMediaController::class, 'grant'])
            ->middleware('throttle:10,1')->name('company-media.capabilities.grant');
        Route::delete('/company-media/capabilities/{grant}', [CompanyMediaController::class, 'revoke'])
            ->middleware('throttle:10,1')->name('company-media.capabilities.revoke');
        Route::post('/company-media/revisions', [CompanyMediaController::class, 'request'])
            ->middleware('throttle:10,1')->name('company-media.revisions.request');
        Route::post('/company-media/revisions/{revision}/decision', [CompanyMediaController::class, 'decide'])
            ->middleware('throttle:10,1')->name('company-media.revisions.decide');
        Route::get('/media', MediaLibraryController::class)->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'upload'])
            ->middleware('throttle:8,1')->name('media.upload');
        Route::get('/media/{asset}', [MediaLibraryController::class, 'preview'])->name('media.preview');
        Route::patch('/media/{asset}/metadata', [MediaLibraryController::class, 'updateMetadata'])
            ->middleware('throttle:20,1')->name('media.metadata.update');
        Route::get('/geography', [GeographyController::class, 'index'])->name('geography.index');
        Route::post('/geography/countries', [GeographyController::class, 'registerCountry'])
            ->middleware('throttle:10,1')->name('geography.countries.store');
        Route::post('/geography/countries/{country}/enable', [GeographyController::class, 'enable'])
            ->middleware('throttle:10,1')->name('geography.countries.enable');
        Route::post('/geography/countries/{country}/disable', [GeographyController::class, 'disable'])
            ->middleware('throttle:10,1')->name('geography.countries.disable');
        Route::post('/geography/countries/{country}/default', [GeographyController::class, 'default'])
            ->middleware('throttle:10,1')->name('geography.countries.default');
        Route::post('/geography/locations', [GeographyController::class, 'storeLocation'])
            ->middleware('throttle:10,1')->name('geography.locations.store');
        Route::put('/geography/locations/{location}', [GeographyController::class, 'updateLocation'])
            ->middleware('throttle:10,1')->name('geography.locations.update');
        Route::get('/catalog', [CatalogModerationController::class, 'index'])->name('catalog.index');
        Route::post('/catalog/companies', [CatalogModerationController::class, 'createCompany'])
            ->middleware('throttle:10,1')->name('catalog.companies.create');
        Route::post('/catalog/companies/{company}/decision', [CatalogModerationController::class, 'companyDecision'])
            ->middleware('throttle:10,1')->name('catalog.companies.decision');
        Route::post('/catalog/projects/{project}/decision', [CatalogModerationController::class, 'projectDecision'])
            ->middleware('throttle:20,1')->name('catalog.projects.decision');
        Route::post('/catalog/listings/{listing}/reject', [CatalogModerationController::class, 'reject'])
            ->middleware('throttle:20,1')->name('catalog.reject');
        Route::post('/catalog/listings/{listing}/unpublish', [CatalogModerationController::class, 'unpublish'])
            ->middleware('throttle:20,1')->name('catalog.unpublish');
        Route::post('/catalog/listings/{listing}/approve', [CatalogModerationController::class, 'approve'])
            ->middleware('throttle:20,1')->name('catalog.approve');
        Route::get('/system/demo-importer', [DemoImportController::class, 'index'])->name('demo.index');
        Route::post('/system/demo-importer/preview', [DemoImportController::class, 'preview'])
            ->middleware('throttle:5,1')->name('demo.preview');
        Route::post('/system/demo-importer/import', [DemoImportController::class, 'install'])
            ->middleware('throttle:2,1')->name('demo.install');
    });
