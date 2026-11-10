<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Companies;

use App\Models\Company;
use App\Services\Developer\CompanyApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CompanyApplicationController
{
    public function store(Request $request, CompanyApplicationService $applications): RedirectResponse
    {
        $company = $applications->createDraft($request->user(), $this->validated($request));

        return redirect()->route('developer.dashboard')
            ->with('notice', 'Company draft #'.$company->getKey().' created. Submit it for review when ready.');
    }

    public function update(
        Request $request,
        Company $company,
        CompanyApplicationService $applications,
    ): RedirectResponse {
        $applications->revise($request->user(), $company, $this->validated($request));

        return redirect()->route('developer.dashboard')
            ->with('notice', 'Company application revised. Review the details and submit.');
    }

    public function submit(
        Request $request,
        Company $company,
        CompanyApplicationService $applications,
    ): RedirectResponse {
        $applications->submit($request->user(), $company);

        return redirect()->route('developer.dashboard')
            ->with('notice', 'Company submitted for administrator review.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'company_type' => ['required', Rule::in(CompanyApplicationService::COMPANY_TYPES)],
            'description' => ['nullable', 'string', 'max:12000'],
        ]);
    }
}
