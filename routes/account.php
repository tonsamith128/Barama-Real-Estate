<?php

declare(strict_types=1);

use App\Http\Controllers\Account\Dashboard\DashboardController;
use App\Http\Controllers\Account\Activity\CustomerActivityController;
use App\Http\Controllers\Account\Inquiries\AccountInquiryController;
use App\Http\Controllers\Account\Notifications\NotificationController;
use App\Http\Controllers\Account\Profile\ProfileController;
use App\Http\Controllers\Account\SavedListings\SavedListingsController;
use App\Http\Controllers\Account\SavedSearches\SavedSearchController;
use App\Http\Controllers\Account\Viewings\ViewingRequestController;
use App\Http\Controllers\Account\Inquiries\AccountInquiryThreadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'throttle:60,1'])
    ->prefix('account')->name('account.')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:10,1')->name('profile.update');
        Route::put('/password', [ProfileController::class, 'password'])
            ->middleware('throttle:5,1')->name('profile.password');

        Route::get('/saved', [SavedListingsController::class, 'index'])->name('saved.index');
        Route::post('/saved/{listing}', [SavedListingsController::class, 'store'])
            ->middleware('throttle:20,1')->name('saved.store');
        Route::delete('/saved/{listing}', [SavedListingsController::class, 'destroy'])
            ->name('saved.destroy');

        Route::get('/searches', [SavedSearchController::class, 'index'])->name('searches.index');
        Route::post('/searches', [SavedSearchController::class, 'store'])
            ->middleware('throttle:12,1')->name('searches.store');
        Route::patch('/searches/{savedSearch}', [SavedSearchController::class, 'rename'])
            ->middleware('throttle:12,1')->name('searches.rename');
        Route::delete('/searches/{savedSearch}', [SavedSearchController::class, 'destroy'])
            ->middleware('throttle:12,1')->name('searches.destroy');

        Route::get('/activity', [CustomerActivityController::class, 'index'])->name('activity.index');
        Route::post('/activity/comparison/{listing}', [CustomerActivityController::class, 'compare'])
            ->middleware('throttle:20,1')->name('activity.comparison.store');
        Route::delete('/activity/comparison/{listing}', [CustomerActivityController::class, 'removeComparison'])
            ->middleware('throttle:20,1')->name('activity.comparison.destroy');
        Route::post('/activity/recent/{listing}', [CustomerActivityController::class, 'remember'])
            ->middleware('throttle:20,1')->name('activity.recent.store');
        Route::delete('/activity/recent/{listing}', [CustomerActivityController::class, 'forget'])
            ->middleware('throttle:20,1')->name('activity.recent.destroy');
        Route::delete('/activity/recent', [CustomerActivityController::class, 'clear'])
            ->middleware('throttle:10,1')->name('activity.recent.clear');

        Route::get('/inquiries', AccountInquiryController::class)->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [AccountInquiryThreadController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/messages', [AccountInquiryThreadController::class, 'store'])
            ->middleware('throttle:8,1')->name('inquiries.messages.store');
        Route::get('/viewings', [ViewingRequestController::class, 'index'])->name('viewings.index');
        Route::post('/viewings/{listing}', [ViewingRequestController::class, 'store'])
            ->middleware('throttle:5,1')->name('viewings.store');
        Route::patch('/viewings/{viewing}/cancel', [ViewingRequestController::class, 'cancel'])
            ->name('viewings.cancel');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->middleware('throttle:10,1')->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');
    });
