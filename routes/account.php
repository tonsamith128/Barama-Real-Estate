<?php

declare(strict_types=1);

use App\Http\Controllers\Account\Dashboard\DashboardController;
use App\Http\Controllers\Account\Inquiries\AccountInquiryController;
use App\Http\Controllers\Account\Notifications\NotificationController;
use App\Http\Controllers\Account\Profile\ProfileController;
use App\Http\Controllers\Account\SavedListings\SavedListingsController;
use App\Http\Controllers\Account\Viewings\ViewingRequestController;
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

        Route::get('/inquiries', AccountInquiryController::class)->name('inquiries.index');
        Route::get('/viewings', [ViewingRequestController::class, 'index'])->name('viewings.index');
        Route::post('/viewings/{listing}', [ViewingRequestController::class, 'store'])
            ->middleware('throttle:5,1')->name('viewings.store');
        Route::patch('/viewings/{viewing}/cancel', [ViewingRequestController::class, 'cancel'])
            ->name('viewings.cancel');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');
    });
