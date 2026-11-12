<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A single property may have independent sale/rent offers. Each offer has its
 * own moderation state; no draft changes a currently published sibling.
 */
final class ListingOfferManager
{
    public function __construct(
        private readonly CompanyCatalogWriter $permissions,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Company $company, Property $property, array $data): Listing
    {
        $this->assertOwner($actor, $company, $property);
        return DB::transaction(function () use ($actor, $company, $property, $data): Listing {
            $locked = $this->lockProperty($actor, $company, $property);
            abort_unless(in_array($locked->status, ['draft', 'approved'], true), 409);
            abort_if($locked->listings()->count() >= 10, 422, 'A property supports up to 10 offers.');

            $offer = $locked->listings()->create(array_merge($this->attributes($data), [
                'slug' => Str::slug($locked->title).'-'.Str::lower(Str::random(10)),
                'status' => 'draft', 'published_at' => null,
            ]));
            $this->revisions->record($actor, 'listing', $offer, 'created', null,
                $this->revisions->snapshot($offer, 'listing'));
            $this->audit($actor, $company, $offer, 'created');
            return $offer;
        });
    }

    /** @param array<string, mixed> $data */
    public function revise(
        User $actor, Company $company, Property $property,
        Listing $offer, array $data,
    ): void {
        $this->assertOwner($actor, $company, $property);
        DB::transaction(function () use ($actor, $company, $property, $offer, $data): void {
            $locked = $this->lockProperty($actor, $company, $property);
            abort_unless(in_array($locked->status, ['draft', 'approved', 'rejected'], true), 409);
            $listing = $locked->listings()->lockForUpdate()->findOrFail($offer->getKey());
            abort_unless(in_array($listing->status, ['draft', 'rejected'], true), 409);

            $before = $this->revisions->snapshot($listing, 'listing');
            $listing->update(array_merge($this->attributes($data), [
                'status' => 'draft', 'published_at' => null,
            ]));
            if ($locked->status === 'rejected') {
                $previousProperty = $this->revisions->snapshot($locked, 'property');
                $locked->update(['status' => 'draft']);
                $this->revisions->record($actor, 'property', $locked, 'revised',
                    $previousProperty, $this->revisions->snapshot($locked, 'property'));
            }
            $this->revisions->record($actor, 'listing', $listing, 'revised',
                $before, $this->revisions->snapshot($listing, 'listing'));
            $this->audit($actor, $company, $listing, 'revised');
        });
    }

    public function submit(User $actor, Company $company, Property $property, Listing $offer): void
    {
        $this->assertOwner($actor, $company, $property);
        DB::transaction(function () use ($actor, $company, $property, $offer): void {
            $locked = $this->lockProperty($actor, $company, $property);
            abort_unless(in_array($locked->status, ['draft', 'approved'], true), 409);
            $listing = $locked->listings()->lockForUpdate()->findOrFail($offer->getKey());
            abort_unless($listing->status === 'draft', 409);

            $before = $this->revisions->snapshot($listing, 'listing');
            $listing->update(['status' => 'pending', 'published_at' => null]);
            $this->revisions->record($actor, 'listing', $listing, 'submitted',
                $before, $this->revisions->snapshot($listing, 'listing'));
            if ($locked->status === 'draft') {
                $previousProperty = $this->revisions->snapshot($locked, 'property');
                $locked->update(['status' => 'pending']);
                $this->revisions->record($actor, 'property', $locked, 'submitted',
                    $previousProperty, $this->revisions->snapshot($locked, 'property'));
            }
            $this->audit($actor, $company, $listing, 'submitted');
        });
    }

    private function assertOwner(User $actor, Company $company, Property $property): void
    {
        $this->permissions->assertCanManage($actor, $company);
        abort_unless($property->company_id === $company->getKey(), 404);
    }

    private function lockProperty(User $actor, Company $company, Property $property): Property
    {
        $locked = Property::query()->lockForUpdate()->findOrFail($property->getKey());
        $this->assertOwner($actor, $company, $locked);
        return $locked;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'transaction_type' => $data['transaction_type'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'pricing_period' => $data['pricing_period'] ?? null,
            'deposit_amount' => $data['deposit_amount'] ?? null,
            'available_from' => $data['available_from'] ?? null,
            'negotiable' => !empty($data['negotiable']),
        ];
    }

    private function audit(User $actor, Company $company, Listing $offer, string $event): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'developer.listing.offer_'.$event,
            'subject_type' => 'listing',
            'subject_id' => $offer->getKey(),
            'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
