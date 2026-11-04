<?php

declare(strict_types=1);

namespace Tests\Feature\Inquiries;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrivateConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function inquiry(string $slug, ?User $customer, ?User $manager = null): Inquiry
    {
        $company = Company::query()->create([
            'name' => 'Agency '.$slug, 'slug' => 'thread-company-'.$slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        if ($manager !== null) {
            CompanyMembership::query()->create([
                'company_id' => $company->id, 'user_id' => $manager->id,
                'role' => 'manager', 'status' => 'active',
            ]);
        }
        $property = Property::query()->create([
            'company_id' => $company->id, 'title' => 'Property '.$slug,
            'reference_code' => 'THREAD-'.$slug, 'property_type' => 'villa', 'status' => 'approved',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'thread-offer-'.$slug,
            'transaction_type' => 'sale', 'status' => 'published',
            'price' => 150000, 'currency' => 'USD', 'published_at' => now()->subHour(),
        ]);

        return Inquiry::query()->create([
            'listing_id' => $listing->id,
            'requester_id' => $customer?->id,
            'full_name' => $customer?->name ?? 'Guest visitor',
            'email' => $customer?->email ?? 'guest@example.test',
            'message' => 'Original private property question.', 'status' => 'new',
        ]);
    }

    public function test_conversations_are_private_to_customer_company_manager_and_administrator(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $one = $this->inquiry('alpha', $customer, $manager);
        $two = $this->inquiry('beta', $otherCustomer, $otherManager);

        $this->get(route('account.inquiries.show', $one))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('account.inquiries.show', $one))
            ->assertOk()->assertSee('Original private property question.');
        $this->get(route('account.inquiries.show', $two))->assertNotFound();
        $this->post(route('account.inquiries.messages.store', $two), [
            'body' => 'Unauthorized cross-account reply',
        ])->assertNotFound();

        $this->actingAs($manager)->get(route('developer.inquiries.show', $one))
            ->assertOk()->assertSee('Property alpha');
        $this->get(route('developer.inquiries.show', $two))->assertNotFound();
        $this->post(route('developer.inquiries.messages.store', $two), [
            'body' => 'Unauthorized cross-company reply',
        ])->assertNotFound();

        $member = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $one->listing->property->company_id,
            'user_id' => $member->id, 'role' => 'member', 'status' => 'active',
        ]);
        $this->actingAs($member)->get(route('developer.inquiries.show', $one))->assertNotFound();
        $this->post(route('developer.inquiries.messages.store', $one), [
            'body' => 'Read only cannot reply',
        ])->assertNotFound();

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.inquiries.show', $two))
            ->assertOk()->assertSee('Property beta');
        $this->actingAs($customer)->get(route('admin.inquiries.show', $one))->assertForbidden();
        $this->assertDatabaseCount('inquiry_messages', 0);
    }

    public function test_customer_and_manager_exchange_messages_with_scoped_in_app_delivery(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $reader = User::factory()->create();
        $inquiry = $this->inquiry('conversation', $customer, $manager);
        CompanyMembership::query()->create([
            'company_id' => $inquiry->listing->property->company_id,
            'user_id' => $reader->id, 'role' => 'member', 'status' => 'active',
        ]);

        $this->actingAs($manager)->post(route('developer.inquiries.messages.store', $inquiry), [
            'body' => 'The villa is available for a viewing.',
        ])->assertRedirect(route('developer.inquiries.show', $inquiry));
        $this->assertSame('responded', $inquiry->fresh()->status);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $customer->id, 'title' => 'New reply to your property inquiry',
        ]);

        $this->actingAs($customer)->get(route('account.inquiries.show', $inquiry))
            ->assertOk()->assertSee('The villa is available for a viewing.');

        $this->post(route('account.inquiries.messages.store', $inquiry), [
            'body' => 'Could I visit on Thursday?',
        ])->assertRedirect(route('account.inquiries.show', $inquiry));
        $this->assertSame('new', $inquiry->fresh()->status);
        $this->assertNull($inquiry->fresh()->responded_at);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $manager->id, 'title' => 'Customer replied to a property inquiry',
        ]);
        $this->assertSame(0, AccountNotification::query()->where('user_id', $reader->id)->count());
        $this->assertDatabaseCount('inquiry_messages', 2);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'inquiry.message.sent', 'subject_type' => 'inquiry',
            'subject_id' => $inquiry->id,
        ]);
        $this->actingAs($manager)->get(route('developer.inquiries.show', $inquiry))
            ->assertSee('Could I visit on Thursday?');
    }

    public function test_guest_inquiry_cannot_receive_in_app_replies(): void
    {
        $manager = User::factory()->create();
        $guestInquiry = $this->inquiry('guest', null, $manager);

        $this->actingAs($manager)->get(route('developer.inquiries.show', $guestInquiry))
            ->assertOk()->assertSee('in-app replies are unavailable');
        $this->post(route('developer.inquiries.messages.store', $guestInquiry), [
            'body' => 'Reply to an anonymous inquiry',
        ])->assertSessionHasErrors('body');
        $this->assertSame('new', $guestInquiry->fresh()->status);
        $this->assertDatabaseCount('inquiry_messages', 0);
        $this->assertDatabaseCount('account_notifications', 0);
    }

    public function test_replies_validate_content_and_escape_untrusted_html(): void
    {
        $customer = User::factory()->create();
        $inquiry = $this->inquiry('html', $customer);
        $this->actingAs($customer)->post(route('account.inquiries.messages.store', $inquiry), [
            'body' => ' ',
        ])->assertSessionHasErrors('body');
        $this->post(route('account.inquiries.messages.store', $inquiry), [
            'body' => str_repeat('a', 3001),
        ])->assertSessionHasErrors('body');

        $this->post(route('account.inquiries.messages.store', $inquiry), [
            'body' => '<script>alert("unsafe")</script>',
        ])->assertRedirect();
        $response = $this->get(route('account.inquiries.show', $inquiry))->assertOk();
        $response->assertDontSee('<script>', false)
            ->assertSee('&lt;script&gt;', false);
        $this->assertSame(1, InquiryMessage::query()->count());
    }
}
