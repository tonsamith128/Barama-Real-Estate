<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyPlanInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class CommercialPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'professional', 'name' => 'Professional',
            'description' => 'Enhanced property publishing',
            'price' => '49.95', 'billing_period' => 'month',
            'listing_limit' => 50, 'sort_order' => 1, 'is_active' => '1',
        ], $overrides);
    }

    private function plan(bool $active = true): CommercialPlan
    {
        return CommercialPlan::query()->create([
            'slug' => 'standard', 'name' => 'Standard',
            'description' => 'Company catalog package',
            'price_cents' => 1999, 'currency' => 'USD',
            'billing_period' => 'month', 'listing_limit' => 20,
            'is_active' => $active,
        ]);
    }

    private function owner(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role, 'status' => 'active',
        ]);
        return $user;
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    public function test_public_pricing_only_shows_published_plans_and_no_checkout(): void
    {
        $this->plan(true);
        CommercialPlan::query()->create([
            'slug' => 'hidden', 'name' => 'Secret Plan',
            'price_cents' => 5000, 'currency' => 'USD',
            'billing_period' => 'year', 'listing_limit' => 1, 'is_active' => false,
        ]);

        $this->get(route('pricing.index'))->assertOk()
            ->assertSee('Standard')->assertSee('$19.99')
            ->assertDontSee('Secret Plan')
            ->assertSee('not an active checkout');
    }

    public function test_administrator_can_create_and_update_pricing_with_exact_minor_units(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.finance.plans.store'), $this->payload())
            ->assertRedirect();
        $plan = CommercialPlan::query()->where('slug', 'professional')->firstOrFail();
        $this->assertSame(4995, $plan->price_cents);
        $this->assertTrue($plan->is_active);
        $this->assertDatabaseHas('audit_events', ['action' => 'finance.plan.created']);

        $this->patch(route('admin.finance.plans.update', $plan), $this->payload([
            'price' => '0.01', 'is_active' => '0', 'listing_limit' => 75,
        ]))->assertRedirect();
        $this->assertSame(1, $plan->fresh()->price_cents);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(75, $plan->fresh()->listing_limit);
        $this->get(route('pricing.index'))->assertDontSee('Professional');
    }

    public function test_plan_validation_and_non_admin_separation(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($customer)->get(route('admin.finance.plans.index'))->assertForbidden();
        $this->post(route('admin.finance.plans.store'), $this->payload())->assertForbidden();

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.finance.plans.store'), $this->payload([
            'price' => '-1', 'slug' => 'Invalid Slug',
        ]))->assertSessionHasErrors(['price', 'slug']);
        $this->post(route('admin.finance.plans.store'), $this->payload())->assertRedirect();
        $this->post(route('admin.finance.plans.store'), $this->payload())
            ->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('commercial_plans', 1);
    }

    public function test_owner_can_request_one_current_plan_without_any_entitlement(): void
    {
        $company = $this->company('owner');
        $owner = $this->owner($company);
        $plan = $this->plan();
        $this->actingAs($owner)->get(route('developer.finance.plans.index', $company))
            ->assertOk()->assertSee('Standard');
        $this->post(route('developer.finance.plans.inquire', [$company, $plan]))->assertRedirect()
            ->assertSessionHas('notice');
        $this->post(route('developer.finance.plans.inquire', [$company, $plan]))->assertRedirect();
        $this->assertDatabaseCount('commercial_plan_inquiries', 1);
        $inquiry = CompanyPlanInquiry::query()->firstOrFail();
        $this->assertSame($company->id, $inquiry->company_id);
        $this->assertSame('pending', $inquiry->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'finance.plan.inquiry']);
    }

    public function test_inactive_plan_and_unrelated_or_non_owner_members_are_denied(): void
    {
        $company = $this->company('target');
        $plan = $this->plan(false);
        $owner = $this->owner($company);
        $this->actingAs($owner)
            ->post(route('developer.finance.plans.inquire', [$company, $plan]))
            ->assertNotFound();

        $member = $this->owner($company, 'member');
        $this->actingAs($member)->get(route('developer.finance.plans.index', $company))
            ->assertForbidden();
        $this->post(route('developer.finance.plans.inquire', [$company, $plan]))
            ->assertForbidden();

        $outsider = $this->owner($this->company('other'));
        $this->actingAs($outsider)->get(route('developer.finance.plans.index', $company))
            ->assertForbidden();
        $this->assertDatabaseCount('commercial_plan_inquiries', 0);
    }

    public function test_published_pricing_can_be_offered_in_homepage_navigation(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.homepage.links.store'), ['link' => 'pricing'])
            ->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()
            ->assertSee('href="'.route('pricing.index').'"', false);
    }
}
