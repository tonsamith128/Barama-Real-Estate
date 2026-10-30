<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CatalogModerationController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        return view('admin.catalog.index', [
            'pending' => Listing::query()->where('status', 'pending')
                ->with('property.company')->orderBy('created_at')->paginate(20),
            'companies' => Company::query()->orderBy('name')->paginate(20, ['*'], 'companies_page'),
        ]);
    }

    public function createCompany(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required','string','min:3','max:190'],
            'slug' => ['required','alpha_dash','max:190',Rule::unique('companies','slug')],
            'owner_email' => ['required','email','max:190',Rule::exists('users','email')],
        ]);
        $owner = User::query()->where('email', $data['owner_email'])
            ->whereNotNull('email_verified_at')->firstOrFail();

        $actorId = $request->user()->getKey();
        DB::transaction(function () use ($owner, $data, $actorId): void {
            $company = Company::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'company_type' => 'developer',
                'status' => 'approved',
                'published_at' => now(),
            ]);
            CompanyMembership::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $owner->getKey(),
                'role' => 'owner',
                'status' => 'active',
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $actorId,
                'action' => 'admin.company.onboarded',
                'subject_type' => 'company',
                'subject_id' => $company->getKey(),
                'context' => json_encode(['owner_id' => $owner->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.catalog.index')
            ->with('notice', 'Company approved and assigned to the verified owner.');
    }

    public function approve(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $actorId = $request->user()->getKey();
        DB::transaction(function () use ($listing, $actorId): void {
            $offer = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $property = $offer->property()->lockForUpdate()->firstOrFail();
            $company = $property->company()->firstOrFail();

            abort_unless($company->status === 'approved' &&
                $company->published_at !== null &&
                $company->published_at->lte(now()), 422);
            abort_unless($property->status === 'pending' && $offer->status === 'pending', 422);

            $property->update(['status' => 'approved']);
            $offer->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $actorId,
                'action' => 'admin.listing.approved',
                'subject_type' => 'listing',
                'subject_id' => $offer->getKey(),
                'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.catalog.index')
            ->with('notice', 'Property and listing approved for public display.');
    }
}
