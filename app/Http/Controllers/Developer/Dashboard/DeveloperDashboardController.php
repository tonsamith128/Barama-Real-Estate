<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Dashboard;

use App\Services\Developer\AccessibleCompanies;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DeveloperDashboardController
{
    public function __invoke(Request $request, AccessibleCompanies $access): View
    {
        return view('developer.dashboard.index', [
            'companies' => $access->forUser($request->user()),
        ]);
    }
}
