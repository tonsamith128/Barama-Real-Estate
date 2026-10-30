<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicPropertyBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function published(string $slug, array $company = [], array $property = [], array $listing = []): Listing
    {
        $firm = Company::query()->create(array_merge([
            'name' => 'Approved Company '.$slug,
            'slug' => 'company-'.$slug,
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ], $company));

        $asset = Property::query()->create(array_merge([
            'company_id' => $firm->getKey(),
            'title' => 'Property '.$slug,
            'reference_code' => 'REF-'.$slug,
            'property_type' => 'house',
            'status' => 'approved',
            'address_line' => 'Battambang, Cambodia',
        ], $property));

        return Listing::query()->create(array_merge([
            'property_id' => $asset->getKey(),
            'slug' => $slug,
            'transaction_type' => 'sale',
            'status' => 'published',
            'price' => 120000,
            'currency' => 'USD',
            'published_at' => now()->subHour(),
        ], $listing));
    }

    public function test_public_marketplace_lists_only_approved_published_offers(): void
    {
        $this->published('visible');
        $this->published('draft-offer', listing: ['status' => 'draft']);
        $this->published('unapproved-property', property: ['status' => 'pending']);
        $this->published('unapproved-company', company: ['status' => 'suspended']);
        $this->published('expired', listing: ['expires_at' => now()->subMinute()]);
        $this->published('future', listing: ['published_at' => now()->addHour()]);
        $this->published('unpublished-company', company: ['published_at' => null]);

        $this->get('/properties')
            ->assertOk()
            ->assertSee('Property visible')
            ->assertDontSee('Property draft-offer')
            ->assertDontSee('Property unapproved-property')
            ->assertDontSee('Property unapproved-company')
            ->assertDontSee('Property expired')
            ->assertDontSee('Property future')
            ->assertDontSee('Property unpublished-company');
    }

    public function test_public_detail_404_for_hidden_listings(): void
    {
        $this->published('public');
        $this->published('hidden', listing: ['status' => 'draft']);

        $this->get('/properties/public')->assertOk()->assertSee('Property public');
        $this->get('/properties/hidden')->assertNotFound();
        $this->get('/properties/absent')->assertNotFound();
    }

    public function test_offers_and_property_types_are_filtered(): void
    {
        $this->published('house-sale');
        $this->published('villa-rent', property: ['property_type' => 'villa'], listing: ['transaction_type' => 'rent']);

        $this->get('/properties?offer=rent&type=villa')
            ->assertOk()
            ->assertSee('Property villa-rent')
            ->assertDontSee('Property house-sale');
    }

    public function test_price_filter_and_sort_are_accepted(): void
    {
        $this->published('low', listing: ['price' => 100000]);
        $this->published('high', listing: ['price' => 200000]);

        $this->get('/properties?min_price=150000&sort=price_desc')
            ->assertOk()
            ->assertSee('Property high')
            ->assertDontSee('Property low');
    }

    public function test_unexpected_sort_and_type_are_rejected(): void
    {
        $this->get('/properties?sort=drop_table&type=not-a-property-type')
            ->assertSessionHasErrors(['sort', 'type']);
    }

    public function test_soft_deleted_listing_and_company_are_not_public(): void
    {
        $one = $this->published('removed-listing');
        $two = $this->published('removed-company');
        $one->delete();
        $two->property->company->delete();

        $this->get('/properties')->assertOk()
            ->assertDontSee('Property removed-listing')
            ->assertDontSee('Property removed-company');
    }
}
