<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Location;
use App\Models\LocationCountry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class MultiOfferCountryGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function firm(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function member(Company $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'role' => 'owner', 'status' => 'active',
        ]);
        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(), 'system_role' => 'super_admin',
        ]);
    }

    private function approvedProperty(Company $company): Property
    {
        return Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'QA-MULTI-'.$company->id,
            'title' => 'Duplex property with multiple offers',
            'property_type' => 'house',
            'status' => 'approved',
        ]);
    }

    private function sale(Property $property): Listing
    {
        return Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'sale-qa-'.$property->id,
            'transaction_type' => 'sale', 'status' => 'published',
            'price' => '240000', 'currency' => 'USD',
            'published_at' => now()->subDay(),
        ]);
    }

    private function rentalFields(): array
    {
        return [
            'transaction_type' => 'rent', 'price' => '1250', 'currency' => 'USD',
            'pricing_period' => 'month', 'deposit_amount' => '2500',
            'available_from' => '2027-01-01',
        ];
    }

    public function test_two_offers_review_independently_and_unpublish_preserves_live_sibling(): void
    {
        $company = $this->firm('two-offers');
        $owner = $this->member($company);
        $property = $this->approvedProperty($company);
        $sale = $this->sale($property);

        $this->actingAs($owner)->post(route('developer.properties.offers.store', [$company, $property]),
            $this->rentalFields())->assertRedirect();
        $rental = Listing::query()->where('transaction_type', 'rent')->firstOrFail();

        $this->assertSame('draft', $rental->status);
        $this->assertSame('published', $sale->fresh()->status);
        $this->assertSame('approved', $property->fresh()->status);

        $this->post(route('developer.properties.offers.submit', [$company, $property, $rental]))
            ->assertRedirect();
        $this->assertSame('pending', $rental->fresh()->status);
        $this->assertSame('published', $sale->fresh()->status);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.catalog.reject', $rental), [
            'reason' => 'The deposit needs a clearer explanation.',
        ])->assertRedirect();
        $this->assertSame('rejected', $rental->fresh()->status);
        $this->assertSame('approved', $property->fresh()->status);
        $this->assertSame('published', $sale->fresh()->status);

        $this->actingAs($owner)->put(route('developer.properties.offers.update', [
            $company, $property, $rental,
        ]), $this->rentalFields())->assertRedirect();
        $this->post(route('developer.properties.offers.submit', [$company, $property, $rental]))
            ->assertRedirect();

        $this->actingAs($admin)->post(route('admin.catalog.approve', $rental))
            ->assertRedirect();
        $this->assertSame('published', $rental->fresh()->status);
        $this->assertSame('published', $sale->fresh()->status);
        $this->assertSame('approved', $property->fresh()->status);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'listing', 'subject_id' => $rental->id,
            'event' => 'approved',
        ]);

        $this->post(route('admin.catalog.unpublish', $sale), [
            'reason' => 'Sale offer no longer available.',
        ])->assertRedirect();
        $this->assertSame('draft', $sale->fresh()->status);
        $this->assertSame('published', $rental->fresh()->status);
        $this->assertSame('approved', $property->fresh()->status);

        $this->post(route('admin.catalog.unpublish', $rental), [
            'reason' => 'Rental offer temporarily closed.',
        ])->assertRedirect();
        $this->assertSame('draft', $property->fresh()->status);
    }

    public function test_other_company_cannot_create_or_modify_offer(): void
    {
        $company = $this->firm('offer-own');
        $other = $this->firm('offer-other');
        $property = $this->approvedProperty($company);
        $sale = $this->sale($property);
        $attacker = $this->member($other);

        $this->actingAs($attacker)->post(route('developer.properties.offers.store', [$company, $property]),
            $this->rentalFields())->assertForbidden();
        $this->put(route('developer.properties.offers.update', [$company, $property, $sale]),
            $this->rentalFields())->assertForbidden();
        $this->assertDatabaseCount('listings', 1);
    }

    public function test_admin_country_governance_reuses_location_hierarchy_and_blocks_unsafe_edits(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.geography.index'))
            ->assertOk()->assertSeeText('Global geographic governance');

        $this->post(route('admin.geography.countries.store'), [
            'country_code' => 'KH', 'iso3' => 'KHM', 'name' => 'Cambodia',
        ])->assertRedirect();
        $kh = LocationCountry::query()->findOrFail('KH');
        $this->assertFalse($kh->enabled);

        $this->post(route('admin.geography.countries.enable', $kh))->assertRedirect();
        $this->post(route('admin.geography.countries.default', $kh))->assertRedirect();
        $this->assertTrue($kh->fresh()->is_default);

        $this->post(route('admin.geography.locations.store'), [
            'name' => 'QA Cambodia', 'slug' => 'qa-cambodia', 'type' => 'country',
            'country_code' => 'KH', 'status' => 'active',
        ])->assertRedirect();
        $country = Location::query()->where('slug', 'qa-cambodia')->firstOrFail();

        $this->post(route('admin.geography.locations.store'), [
            'name' => 'QA Battambang', 'slug' => 'qa-battambang', 'type' => 'province',
            'parent_id' => $country->id, 'country_code' => 'KH', 'status' => 'active',
        ])->assertRedirect();
        $province = Location::query()->where('slug', 'qa-battambang')->firstOrFail();

        $this->put(route('admin.geography.locations.update', $country), [
            'name' => 'QA Cambodia', 'slug' => 'qa-cambodia', 'type' => 'country',
            'parent_id' => $province->id, 'country_code' => 'KH', 'status' => 'active',
        ])->assertSessionHasErrors('parent_id');

        $this->post(route('admin.geography.countries.disable', $kh))
            ->assertStatus(422);
        $this->assertTrue($kh->fresh()->enabled);
        $this->assertNotEmpty($province->fresh()->uuid);
    }

    public function test_non_admin_cannot_create_country_or_edit_location(): void
    {
        $company = $this->firm('role-test');
        $user = $this->member($company);
        $this->actingAs($user)->post(route('admin.geography.countries.store'), [
            'country_code' => 'KH', 'iso3' => 'KHM', 'name' => 'Cambodia',
        ])->assertForbidden();
        $this->assertDatabaseCount('location_countries', 0);
    }
}
