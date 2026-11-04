<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardPortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Agency '.$slug, 'slug' => $slug, 'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
    }

    private function addProperty(Company $company, string $slug): Listing
    {
        $property = Property::query()->create([
            'company_id' => $company->id, 'reference_code' => 'PORT-'.$slug,
            'title' => 'Private Property '.$slug, 'property_type' => 'villa', 'status' => 'pending',
        ]);
        return Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'offer-'.$slug,
            'transaction_type' => 'sale', 'status' => 'pending', 'price' => 150000, 'currency' => 'USD',
        ]);
    }

    public function test_owner_sees_only_their_company_portfolio_and_managed_leads(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->company('alpha');
        $second = $this->company('beta');
        foreach ([[$first, $owner], [$second, $other]] as [$company, $user]) {
            CompanyMembership::query()->create([
                'company_id' => $company->id, 'user_id' => $user->id,
                'role' => 'owner', 'status' => 'active',
            ]);
        }
        Project::query()->create([
            'company_id' => $first->id, 'name' => 'Alpha Project', 'slug' => 'alpha-project',
            'status' => 'draft', 'project_type' => 'residential',
        ]);
        $one = $this->addProperty($first, 'alpha');
        $two = $this->addProperty($second, 'beta');
        foreach ([[$one, 'Alpha Buyer'], [$two, 'Private Beta Buyer']] as [$listing, $name]) {
            Inquiry::query()->create([
                'listing_id' => $listing->id, 'full_name' => $name,
                'email' => 'buyer@example.test', 'message' => 'Interested',
                'status' => 'new',
            ]);
        }

        $this->actingAs($owner)->get(route('developer.dashboard'))
            ->assertOk()
            ->assertSee('Agency alpha')
            ->assertDontSee('Agency beta')
            ->assertSee('Private Property alpha')
            ->assertDontSee('Private Property beta')
            ->assertSee('Manage properties')
            ->assertSee('Manage projects')
            ->assertSee('Manage team')
            ->assertSee('New company inquiries');
    }

    public function test_read_only_company_member_never_sees_management_actions_or_leads(): void
    {
        $member = User::factory()->create();
        $company = $this->company('read-only');
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $member->id,
            'role' => 'member', 'status' => 'active',
        ]);
        $listing = $this->addProperty($company, 'read-only');
        Inquiry::query()->create([
            'listing_id' => $listing->id, 'full_name' => 'Private Lead',
            'email' => 'private@example.test', 'message' => 'Confidential lead',
            'status' => 'new',
        ]);

        $this->actingAs($member)->get(route('developer.dashboard'))
            ->assertOk()
            ->assertSee('Agency read-only')
            ->assertSee('read-only access')
            ->assertDontSee('Manage properties')
            ->assertDontSee('Manage projects')
            ->assertDontSee('Manage team')
            ->assertDontSee('Private Lead');
    }
}
