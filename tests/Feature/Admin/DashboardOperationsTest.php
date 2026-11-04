<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_sees_real_review_queues_and_selectable_activity_period(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $customer = User::factory()->create(['name' => 'Customer Appointment']);
        $company = Company::query()->create([
            'name' => 'Operations Test Company', 'slug' => 'operations-test-company',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        Project::query()->create([
            'company_id' => $company->id, 'name' => 'Review Me Project',
            'slug' => 'review-me-project', 'status' => 'pending', 'project_type' => 'residential',
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id, 'reference_code' => 'DASH-OPS-1',
            'title' => 'Review Me Property', 'property_type' => 'villa', 'status' => 'pending',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'review-me-offer',
            'transaction_type' => 'sale', 'status' => 'pending', 'price' => 200000, 'currency' => 'USD',
        ]);
        Inquiry::query()->create([
            'listing_id' => $listing->id, 'requester_id' => $customer->id,
            'full_name' => 'Operations Contact', 'email' => 'operations@example.test',
            'message' => 'Please send details.', 'status' => 'new',
        ]);
        ViewingRequest::query()->create([
            'user_id' => $customer->id, 'listing_id' => $listing->id,
            'requested_at' => now()->addDays(3), 'status' => 'pending',
        ]);
        ContactMessage::query()->create([
            'full_name' => 'Web Enquiry', 'email' => 'web@example.test',
            'subject' => 'Need help with registration', 'message' => 'Please help.',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard', ['days' => 30]))
            ->assertOk()
            ->assertSee('Your review queue')
            ->assertSee('Pending projects')
            ->assertSee('Pending listings')
            ->assertSee('Marketplace activity')
            ->assertSee('30 days')
            ->assertSee('Review Me Project')
            ->assertSee('Review Me Property')
            ->assertSee('Customer Appointment')
            ->assertSee('View website');
    }

    public function test_unsupported_activity_window_falls_back_to_seven_days_and_admin_access_is_protected(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.dashboard', ['days' => '99999']))
            ->assertOk()->assertSee('past 7 days');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard', ['days' => 30]))->assertForbidden();
    }
}
