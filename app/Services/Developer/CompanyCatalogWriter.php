<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompanyCatalogWriter
{
    public function __construct(
        private readonly CatalogLocationPolicy $locations,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    private function checkRelatedFields(Company $company, array $fields): void
    {
        $this->locations->assertSelectable(isset($fields['location_id'])
            ? (int) $fields['location_id'] : null);
        if (!empty($fields['project_id'])) {
            abort_unless(Project::query()
                ->whereKey($fields['project_id'])
                ->where('company_id', $company->getKey())
                ->exists(), 422, 'The selected development project belongs to another company.');
        }
    }

    private function propertyAttributes(array $data): array
    {
        return [
            'project_id' => $data['project_id'] ?? null,
            'reference_code' => $data['reference_code'],
            'title' => $data['title'],
            'property_type' => $data['property_type'],
            'description' => $data['description'] ?? null,
            'address_line' => $data['address_line'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? null,
            'bathrooms' => $data['bathrooms'] ?? null,
            'parking_spaces' => $data['parking_spaces'] ?? null,
            'floor_number' => $data['floor_number'] ?? null,
            'total_floors' => $data['total_floors'] ?? null,
            'area_value' => $data['area_value'] ?? null,
            'area_unit' => $data['area_unit'] ?? null,
            'land_area_value' => $data['land_area_value'] ?? null,
            'land_area_unit' => $data['land_area_unit'] ?? null,
            'furnishing_status' => $data['furnishing_status'] ?? null,
            'year_built' => $data['year_built'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ];
    }

    private function listingAttributes(array $data): array
    {
        return [
            'transaction_type' => $data['transaction_type'],
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'USD',
            'pricing_period' => $data['pricing_period'] ?? null,
            'deposit_amount' => $data['deposit_amount'] ?? null,
            'available_from' => $data['available_from'] ?? null,
            'negotiable' => !empty($data['negotiable']),
        ];
    }

    public function assertCanManage(User $user, Company $company): void
    {
        $canManage = $company->memberships()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
        abort_unless($canManage && $company->status === 'approved', 403);
    }

    public function create(User $user, Company $company, array $attributes): Listing
    {
        $this->assertCanManage($user, $company);
        $this->checkRelatedFields($company, $attributes);
        $slug = Str::slug((string) $attributes['title']).'-'.Str::lower(Str::random(8));
        return DB::transaction(function () use ($company, $attributes, $slug, $user): Listing {
            $this->assertCanManage($user, $company);
            $property = Property::query()->create(array_merge([
                'company_id' => $company->getKey(),
                'status' => 'draft',
            ], $this->propertyAttributes($attributes)));

            $listing = Listing::query()->create(array_merge([
                'property_id' => $property->getKey(),
                'slug' => $slug,
                'status' => 'draft',
                'published_at' => null,
            ], $this->listingAttributes($attributes)));

            $this->revisions->record($user, 'property', $property, 'created', null,
                $this->revisions->snapshot($property, 'property'));
            $this->revisions->record($user, 'listing', $listing, 'created', null,
                $this->revisions->snapshot($listing, 'listing'));
            DB::table('audit_events')->insert([
                'actor_id' => $user->getKey(),
                'action' => 'developer.listing.created',
                'subject_type' => 'listing',
                'subject_id' => $listing->getKey(),
                'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $listing;
        });
    }

    public function reviseDraft(User $user, Company $company, Property $property, array $data): void
    {
        $this->assertCanManage($user, $company);
        abort_unless($property->company_id === $company->getKey(), 404);
        $this->checkRelatedFields($company, $data);

        DB::transaction(function () use ($user, $company, $property, $data): void {
            $locked = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $this->assertCanManage($user, $company);
            abort_unless($locked->company_id === $company->getKey(), 404);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
            $listing = $locked->listings()->lockForUpdate()->firstOrFail();
            abort_unless(in_array($listing->status, ['draft', 'rejected'], true), 409);

            $oldProperty = $this->revisions->snapshot($locked, 'property');
            $oldListing = $this->revisions->snapshot($listing, 'listing');
            $locked->update(array_merge($this->propertyAttributes($data), [
                'status' => 'draft',
            ]));
            $listing->update(array_merge($this->listingAttributes($data), [
                'status' => 'draft',
                'published_at' => null,
            ]));
            $this->revisions->record($user, 'property', $locked, 'revised', $oldProperty,
                $this->revisions->snapshot($locked, 'property'));
            $this->revisions->record($user, 'listing', $listing, 'revised', $oldListing,
                $this->revisions->snapshot($listing, 'listing'));
            DB::table('audit_events')->insert([
                'actor_id' => $user->getKey(),
                'action' => 'developer.property.revised',
                'subject_type' => 'property',
                'subject_id' => $locked->getKey(),
                'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function submitForReview(User $user, Company $company, Property $property): void
    {
        $this->assertCanManage($user, $company);
        abort_unless($property->company_id === $company->getKey(), 404);

        DB::transaction(function () use ($property, $company, $user): void {
            $locked = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $this->assertCanManage($user, $company);
            abort_unless($locked->company_id === $company->getKey(), 404);
            abort_unless($locked->status === 'draft', 409);
            $this->locations->assertSelectable($locked->location_id !== null
                ? (int) $locked->location_id : null);

            $listings = $locked->listings()->lockForUpdate()->get();
            abort_unless($listings->isNotEmpty(), 409);
            foreach ($listings as $listing) {
                abort_unless($listing->status === 'draft', 409);
            }

            $previousProperty = $this->revisions->snapshot($locked, 'property');
            $locked->update(['status' => 'pending']);
            $this->revisions->record($user, 'property', $locked, 'submitted',
                $previousProperty, $this->revisions->snapshot($locked, 'property'));

            foreach ($listings as $listing) {
                $before = $this->revisions->snapshot($listing, 'listing');
                $listing->update(['status' => 'pending', 'published_at' => null]);
                $this->revisions->record($user, 'listing', $listing, 'submitted',
                    $before, $this->revisions->snapshot($listing, 'listing'));
            }

            DB::table('audit_events')->insert([
                'actor_id' => $user->getKey(),
                'action' => 'developer.property.submitted',
                'subject_type' => 'property',
                'subject_id' => $locked->getKey(),
                'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
