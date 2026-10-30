<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CompanyCatalogWriter
{
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
        $slug = Str::slug((string) $attributes['title']).'-'.Str::lower(Str::random(8));
        return DB::transaction(function () use ($company, $attributes, $slug, $user): Listing {
            $property = Property::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $attributes['project_id'] ?? null,
                'reference_code' => $attributes['reference_code'],
                'title' => $attributes['title'],
                'property_type' => $attributes['property_type'],
                'description' => $attributes['description'] ?? null,
                'address_line' => $attributes['address_line'] ?? null,
                'bedrooms' => $attributes['bedrooms'] ?? null,
                'bathrooms' => $attributes['bathrooms'] ?? null,
                'area_value' => $attributes['area_value'] ?? null,
                'area_unit' => $attributes['area_unit'] ?? null,
                'status' => 'draft',
            ]);

            $listing = Listing::query()->create([
                'property_id' => $property->getKey(),
                'slug' => $slug,
                'transaction_type' => $attributes['transaction_type'],
                'status' => 'draft',
                'price' => $attributes['price'],
                'currency' => $attributes['currency'] ?? 'USD',
                'pricing_period' => $attributes['pricing_period'] ?? null,
                'negotiable' => !empty($attributes['negotiable']),
                'published_at' => null,
            ]);
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

    public function submitForReview(User $user, Company $company, Property $property): void
    {
        $this->assertCanManage($user, $company);
        abort_unless($property->company_id === $company->getKey(), 404);
        DB::transaction(function () use ($property, $user): void {
            if ($property->status !== 'draft') {
                throw new RuntimeException('Only draft properties can be submitted for review.');
            }
            $property->update(['status' => 'pending']);
            $property->listings()->where('status', 'draft')->update(['status' => 'pending']);
            DB::table('audit_events')->insert([
                'actor_id' => $user->getKey(),
                'action' => 'developer.property.submitted',
                'subject_type' => 'property',
                'subject_id' => $property->getKey(),
                'context' => json_encode(['company_id' => $property->company_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
