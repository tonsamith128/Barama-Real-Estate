<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ViewingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function requestForCompany(string $slug, User $customer): ViewingRequest
    {
        $company = Company::query()->create([
            'name' => 'Agency '.$slug,
            'slug' => 'agency-'.$slug,
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'REF-'.$slug,
            'title' => 'Property '.$slug,
            'property_type' => 'villa',
            'status' => 'approved',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id,
            'slug' => 'listing-'.$slug,
            'transaction_type' => 'sale',
            'status' => 'published',
            'price' => 100000,
            'currency' => 'USD',
            'published_at' => now()->subHour(),
        ]);
        return ViewingRequest::query()->create([
            'user_id' => $customer->id,
            'listing_id' => $listing->id,
            'requested_at' => now()->addDays(4),
            'status' => 'pending',
            'message' => 'Please arrange a visit.',
        ]);
    }

    private function member(User $user, ViewingRequest $viewing, string $role = 'manager'): void
    {
        CompanyMembership::query()->create([
            'company_id' => $viewing->listing->property->company_id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    public function test_admin_dashboard_works_with_customer_module_tables_not_yet_migrated(): void
    {
        Schema::drop('contact_messages');
        Schema::drop('viewing_requests');
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Database update required')
            ->assertSee('contact_messages')
            ->assertSee('viewing_requests')
            ->assertSee('php artisan migrate --force');
    }

    public function test_admin_can_confirm_and_notify_and_cannot_confirm_twice(): void
    {
        $customer = User::factory()->create();
        $viewing = $this->requestForCompany('alpha', $customer);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)
            ->get(route('admin.viewings.index'))->assertOk()->assertSee('Property alpha');
        $this->post(route('admin.viewings.decide', $viewing), ['decision' => 'confirmed'])
            ->assertRedirect();
        $this->assertSame('confirmed', $viewing->fresh()->status);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $customer->id,
            'title' => 'Viewing request confirmed',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'viewing.confirmed',
            'subject_id' => $viewing->id,
        ]);
        $this->post(route('admin.viewings.decide', $viewing), ['decision' => 'declined'])
            ->assertSessionHasErrors('decision');
        $this->assertSame(1, AccountNotification::query()->count());
    }

    public function test_developer_manager_can_only_access_their_company_requests(): void
    {
        $customer = User::factory()->create();
        $own = $this->requestForCompany('own', $customer);
        $other = $this->requestForCompany('other', $customer);
        $manager = User::factory()->create();
        $this->member($manager, $own);
        $this->actingAs($manager)->get(route('developer.viewings.index'))
            ->assertOk()->assertSee('Property own')->assertDontSee('Property other');
        $this->post(route('developer.viewings.decide', $other), ['decision' => 'confirmed'])
            ->assertNotFound();
        $this->post(route('developer.viewings.decide', $own), ['decision' => 'declined'])
            ->assertRedirect();
        $this->assertSame('declined', $own->fresh()->status);
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_ordinary_customer_and_read_only_members_cannot_decide(): void
    {
        $viewing = $this->requestForCompany('protected', User::factory()->create());
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->get(route('admin.viewings.index'))->assertForbidden();
        $this->post(route('admin.viewings.decide', $viewing), ['decision' => 'confirmed'])
            ->assertForbidden();
        $this->post(route('developer.viewings.decide', $viewing), ['decision' => 'declined'])
            ->assertNotFound();
        $this->member($visitor, $viewing, 'viewer');
        $this->post(route('developer.viewings.decide', $viewing), ['decision' => 'confirmed'])
            ->assertNotFound();
    }

    public function test_cancelled_and_past_visits_cannot_be_confirmed(): void
    {
        $viewing = $this->requestForCompany('old', User::factory()->create());
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $viewing->update(['status' => 'cancelled']);
        $this->actingAs($admin)
            ->post(route('admin.viewings.decide', $viewing), ['decision' => 'confirmed'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('cancelled', $viewing->fresh()->status);

        $viewing->update(['status' => 'pending', 'requested_at' => now()->subDay()]);
        $this->post(route('admin.viewings.decide', $viewing), ['decision' => 'confirmed'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $viewing->fresh()->status);
    }
}
