<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InquiryReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function inquiry(string $id, User $customer): Inquiry
    {
        $company = Company::query()->create([
            'name' => 'Agency '.$id, 'slug' => 'company-'.$id,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id, 'reference_code' => 'REF-'.$id,
            'title' => 'Property '.$id, 'property_type' => 'villa', 'status' => 'approved',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'listing-'.$id,
            'transaction_type' => 'rent', 'status' => 'published',
            'price' => 850, 'currency' => 'USD', 'published_at' => now()->subHour(),
        ]);
        return Inquiry::query()->create([
            'listing_id' => $listing->id, 'requester_id' => $customer->id,
            'full_name' => $customer->name, 'email' => $customer->email,
            'message' => 'A private inquiry about this property.', 'status' => 'new',
        ]);
    }

    public function test_admin_can_review_once_and_creates_audit_and_customer_notification(): void
    {
        $customer = User::factory()->create();
        $inquiry = $this->inquiry('one', $customer);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.inquiries.respond', $inquiry))->assertRedirect();
        $this->assertSame('responded', $inquiry->fresh()->status);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $customer->id, 'title' => 'Property inquiry reviewed',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => 'inquiry', 'subject_id' => $inquiry->id, 'action' => 'inquiry.responded',
        ]);
        $this->patch(route('admin.inquiries.respond', $inquiry))->assertSessionHasErrors('inquiry');
        $this->assertSame(1, AccountNotification::query()->count());
    }

    public function test_developer_must_have_active_manager_or_owner_membership(): void
    {
        $customer = User::factory()->create();
        $first = $this->inquiry('first', $customer);
        $second = $this->inquiry('second', $customer);
        $manager = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $first->listing->property->company_id,
            'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active',
        ]);
        $this->actingAs($manager)
            ->patch(route('developer.inquiries.respond', $second))->assertNotFound();
        $this->patch(route('developer.inquiries.respond', $first))->assertRedirect();
        $this->assertSame('responded', $first->fresh()->status);
        $this->assertSame('new', $second->fresh()->status);
    }

    public function test_customer_cannot_mark_inquiry_handled(): void
    {
        $customer = User::factory()->create();
        $inquiry = $this->inquiry('protected', $customer);
        $this->actingAs($customer)
            ->patch(route('admin.inquiries.respond', $inquiry))->assertForbidden();
        $this->patch(route('developer.inquiries.respond', $inquiry))->assertNotFound();
    }
}
