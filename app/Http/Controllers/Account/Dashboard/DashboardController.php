<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Dashboard;

use Illuminate\Contracts\View\View;

final class DashboardController
{
    public function __invoke(): View
    {
        return view('account.dashboard.index');
    }
}
