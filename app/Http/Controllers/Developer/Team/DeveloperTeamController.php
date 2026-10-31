<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Team;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Services\Developer\CompanyTeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DeveloperTeamController
{
    public function index(Request $request, Company $company, CompanyTeamManager $teams): View
    {
        $teams->assertOwner($request->user(), $company);
        return view('developer.team.index', [
            'company' => $company,
            'memberships' => $company->memberships()->with('user')
                ->orderBy('role')->orderBy('created_at')->paginate(20),
        ]);
    }

    public function assign(Request $request, Company $company, CompanyTeamManager $teams): RedirectResponse
    {
        $teams->assertOwner($request->user(), $company);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(['manager', 'member'])],
        ]);
        $teams->assign($request->user(), $company, $data['email'], $data['role']);
        return back()->with('notice', 'Verified team member added or updated.');
    }

    public function deactivate(
        Request $request,
        Company $company,
        CompanyMembership $membership,
        CompanyTeamManager $teams,
    ): RedirectResponse {
        $teams->deactivate($request->user(), $company, $membership);
        return back()->with('notice', 'Team membership deactivated.');
    }
}
