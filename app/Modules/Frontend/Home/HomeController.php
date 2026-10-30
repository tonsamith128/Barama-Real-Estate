<?php

declare(strict_types=1);

namespace App\Modules\Frontend\Home;

use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(): View
    {
        return view('frontend.home.index');
    }
}

