<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function published(string $slug): Listing
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => 'company-'.$slug,
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->getKey(),
            'reference_code' => 'PROP-'.$slug,
            'title' => 'Property '.$slug,
            'property_type' => 'villa',
            'status' => 'approved',
        ]);
        return Listing::query()->create([
            'property_id' => $property->getKey(),
            'slug' => $slug,
            'transaction_type' => 'sale',
            'price' => 300000,
            'currency' => 'USD',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    private function inquiryData(): array
    {
        return [
            'full_name' => 'Test Customer',
            'email' => 'customer@example.com',
            'message' => 'I would like more information about viewing this property.',
            'consent' => '1',
        ];
    }

    public function test_guest_can_submit_inquiry_to_public_listing(): void
    {
        $listing = $this->published('sale-villa');
        $this->post(route('properties.inquiries.store', $listing->slug), $this->inquiryData())
            ->assertRedirect(route('properties.show', $listing->slug));
        $this->assertDatabaseHas('inquiries', [
            'listing_id' => $listing->getKey(),
            'email' => 'customer@example.com',
            'status' => 'new',
        ]);
        $this->assertSame(1, Inquiry::query()->count());
    }

    public function test_hidden_listing_and_invalid_consent_cannot_create_inquiry(): void
    {
        $listing = $this->published('hidden');
        $listing->update(['status' => 'draft']);
        $this->post(route('properties.inquiries.store', $listing->slug), $this->inquiryData())
            ->assertNotFound();
        $this->assertDatabaseCount('inquiries', 0);

        $listing->update(['status' => 'published']);
        $data = $this->inquiryData();
        unset($data['consent']);
        $this->post(route('properties.inquiries.store', $listing->slug), $data)
            ->assertSessionHasErrors(['consent']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_developer_inbox_is_company_scoped_and_admin_sees_all(): void
    {
        $own = $this->published('own');
        $other = $this->published('other');

        Inquiry::query()->create([
            'listing_id' => $own->getKey(),
            'full_name' => 'Visible Inquiry', 'email' => 'visible@example.com',
            'message' => 'This is for the current developer',
            'status' => 'new',
        ]);
        Inquiry::query()->create([
            'listing_id' => $other->getKey(),
            'full_name' => 'Hidden Inquiry', 'email' => 'hidden@example.com',
            'message' => 'This belongs to another company',
            'status' => 'new',
        ]);

        $developer = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $own->property->company->getKey(),
            'user_id' => $developer->getKey(),
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->actingAs($developer)->get(route('developer.inquiries.index'))
            ->assertOk()
            ->assertSee('Visible Inquiry')
            ->assertDontSee('Hidden Inquiry');
        $this->get(route('admin.inquiries.index'))->assertForbidden();

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.inquiries.index'))
            ->assertOk()
            ->assertSee('Visible Inquiry')
            ->assertSee('Hidden Inquiry');
    }
}
