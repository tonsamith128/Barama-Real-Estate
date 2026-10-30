<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\Companies\CompanyController;
use App\Http\Controllers\Frontend\Home\HomeController;
use App\Http\Controllers\Frontend\Projects\ProjectController;
use App\Http\Controllers\Frontend\Properties\PropertyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\Inquiries\InquiryController;
use App\Http\Controllers\Frontend\Media\PublicDemoMediaController;

Route::get('/', HomeController::class)->name('home');

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{slug}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/company', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/company/{slug}', [CompanyController::class, 'show'])->name('companies.show');

Route::get('/media/demo/{digest}', PublicDemoMediaController::class)->name('media.demo.show');

Route::post('/properties/{slug}/inquire', [InquiryController::class, 'store'])
    ->middleware('throttle:5,1')->name('properties.inquiries.store');
