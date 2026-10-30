<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\Home\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

use App\Http\Controllers\Frontend\Properties\PropertyController;

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{slug}', [PropertyController::class, 'show'])->name('properties.show');
