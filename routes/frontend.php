<?php

declare(strict_types=1);

use App\Modules\Frontend\Home\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

