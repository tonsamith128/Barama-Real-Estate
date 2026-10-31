<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Finance;

use App\Models\Company;
use App\Models\CompanyBillingProfile;
use App\Services\Billing\GlobalBillingPreferences;
use App\Services\Developer\CompanyTeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanyBillingProfileController
{
    public function edit(Request $request, Company $company, CompanyTeamManager $teams): View
    {
        $teams->assertOwner($request->user(), $company);
        abort_unless(Schema::hasTable('company_billing_profiles'), 503, 'Run billing migrations.');

        $profile = CompanyBillingProfile::query()->where('company_id', $company->getKey())->first();

        return view('developer.finance.profile.edit', [
            'company' => $company,
            'profile' => $profile,
            'billingLocale' => $profile?->preferred_locale ?? 'en',
            'countries' => config('billing_countries'),
            'locales' => GlobalBillingPreferences::LOCALES,
            'currencies' => GlobalBillingPreferences::CURRENCIES,
        ]);
    }

    public function update(
        Request $request, Company $company, CompanyTeamManager $teams
    ): RedirectResponse {
        $teams->assertOwner($request->user(), $company);
        abort_unless(Schema::hasTable('company_billing_profiles'), 503, 'Run billing migrations.');

        $data = $request->validate([
            'billing_name' => ['required', 'string', 'min:2', 'max:160'],
            'billing_email' => ['required', 'email:rfc', 'max:190'],
            'country_code' => ['required', 'string', Rule::in(GlobalBillingPreferences::COUNTRIES)],
            'address_line' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'preferred_locale' => ['required', Rule::in(array_keys(GlobalBillingPreferences::LOCALES))],
            'preferred_currency' => ['required', Rule::in(GlobalBillingPreferences::CURRENCIES)],
            'timezone' => ['required', 'timezone', 'max:64'],
        ]);

        DB::transaction(function () use ($request, $company, $data): void {
            Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            CompanyBillingProfile::query()->updateOrCreate(
                ['company_id' => $company->getKey()], $data,
            );
            // Never log contact details, address, postal code or billing email.
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->getKey(),
                'action' => 'billing.profile.updated',
                'subject_type' => 'company',
                'subject_id' => $company->getKey(),
                'context' => json_encode(['country_code' => $data['country_code']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('developer.finance.profile.edit', $company)
            ->with('notice', 'Billing preferences saved. Payment currency remains USD; tax eligibility is not determined.');
    }
}
