<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\Companies\CompanyController;
use App\Http\Controllers\Frontend\Home\HomeController;
use App\Http\Controllers\Frontend\Projects\ProjectController;
use App\Http\Controllers\Frontend\Properties\PropertyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\Seo\SitemapController;
use App\Http\Controllers\Frontend\Pages\PublicPageController;
use App\Http\Controllers\Frontend\Legal\LegalPageController;
use App\Http\Controllers\Frontend\Inquiries\InquiryController;
use App\Http\Controllers\Frontend\Media\PublicDemoMediaController;
use App\Http\Controllers\Frontend\Media\PublicHomepageMediaController;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('seo.robots');
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/contact', [PublicPageController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicPageController::class, 'submitContact'])
    ->middleware('throttle:4,1')->name('contact.submit');
Route::get('/faq', [PublicPageController::class, 'faq'])->name('faq');
Route::get('/legal', [LegalPageController::class, 'index'])->name('legal.index');
Route::get('/legal/{slug}', [LegalPageController::class, 'show'])->name('legal.show');

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{slug}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/company', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/company/{slug}', [CompanyController::class, 'show'])->name('companies.show');

Route::get('/media/homepage/{asset}', PublicHomepageMediaController::class)
    ->name('media.homepage.show');

Route::get('/media/demo/{digest}', PublicDemoMediaController::class)->name('media.demo.show');

Route::post('/properties/{slug}/inquire', [InquiryController::class, 'store'])
    ->middleware('throttle:5,1')->name('properties.inquiries.store');
