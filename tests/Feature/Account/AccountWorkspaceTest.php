<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Property;
use App\Models\SavedListing;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function published(string $slug = 'test-property'): Listing
    {
        $company = Company::query()->create([
            'name' => 'Test Agency '.$slug,
            'slug' => 'agency-'.$slug,
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'REF-'.$slug,
            'title' => 'Published Test Property',
            'property_type' => 'villa',
            'status' => 'approved',
        ]);
        return Listing::query()->create([
            'property_id' => $property->id,
            'slug' => $slug,
            'transaction_type' => 'sale',
            'status' => 'published',
            'price' => '150000.00',
            'currency' => 'USD',
            'published_at' => now()->subHour(),
        ]);
    }

    public function test_guests_cannot_access_account_pages_or_mutations(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->get(route('account.saved.index'))->assertRedirect(route('login'));
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->get(route('account.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_real_dashboard_and_account_pages_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('account.dashboard'))->assertOk()->assertSee('Welcome back')
            ->assertSee('Saved homes')->assertSee('Viewing requests');
        $this->get(route('account.saved.index'))->assertOk();
        $this->get(route('account.inquiries.index'))->assertOk();
        $this->get(route('account.viewings.index'))->assertOk();
        $this->get(route('account.notifications.index'))->assertOk();
        $this->get(route('account.profile.edit'))->assertOk();
    }

    public function test_save_is_idempotent_private_and_publication_gated(): void
    {
        $listing = $this->published();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)
            ->post(route('account.saved.store', $listing))->assertRedirect();
        $this->post(route('account.saved.store', $listing))->assertRedirect();
        $this->assertDatabaseCount('saved_listings', 1);
        $this->get(route('account.saved.index'))->assertSee('Published Test Property');

        $this->actingAs($second)
            ->get(route('account.saved.index'))->assertDontSee('Published Test Property');
        $this->delete(route('account.saved.destroy', $listing))->assertRedirect();
        $this->assertDatabaseCount('saved_listings', 1);

        $listing->update(['status' => 'draft']);
        $this->post(route('account.saved.store', $listing))->assertNotFound();
        $this->actingAs($first)->get(route('account.saved.index'))
            ->assertDontSee('Published Test Property');
        $this->delete(route('account.saved.destroy', $listing))->assertRedirect();
        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_viewing_request_validates_publish_state_and_account_ownership(): void
    {
        $listing = $this->published('viewing');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('account.viewings.store', $listing), [
            'requested_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'message' => 'Please schedule a tour.',
        ])->assertRedirect(route('account.viewings.index'));

        $viewing = ViewingRequest::query()->firstOrFail();
        $this->assertSame('pending', $viewing->status);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $owner->id,
            'type' => 'viewing',
        ]);
        $this->actingAs($other)->get(route('account.viewings.index'))
            ->assertDontSee('Published Test Property');
        $this->patch(route('account.viewings.cancel', $viewing))->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('account.viewings.cancel', $viewing))->assertRedirect();
        $this->assertSame('cancelled', $viewing->fresh()->status);
        $this->post(route('account.viewings.store', $listing), [
            'requested_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('requested_at');
    }

    public function test_inquiry_history_only_shows_requests_from_signed_in_user(): void
    {
        $listing = $this->published('inquiry');
        $first = User::factory()->create();
        $second = User::factory()->create();
        Inquiry::query()->create([
            'listing_id' => $listing->id,
            'requester_id' => $first->id,
            'full_name' => 'One User',
            'email' => 'one@example.test',
            'message' => 'My private message about this property.',
            'status' => 'new',
        ]);
        $this->actingAs($second)->get(route('account.inquiries.index'))
            ->assertDontSee('My private message');
        $this->actingAs($first)->get(route('account.inquiries.index'))
            ->assertSee('My private message');
    }

    public function test_contact_form_stores_message_and_admin_alone_can_read_inbox(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('About Barama Real Estate');
        $this->get(route('faq'))->assertOk()->assertSee('Frequently asked questions');
        $this->get(route('contact'))->assertOk()->assertSee('Contact Barama Real Estate');
        $this->post(route('contact.submit'), [
            'full_name' => 'Prospective Customer',
            'email' => 'customer@example.test',
            'subject' => 'Question about accounts',
            'message' => 'Please tell me how to contact a company.',
            'consent' => '1',
            'website' => '',
        ])->assertRedirect(route('contact'));
        $this->assertDatabaseCount('contact_messages', 1);
        $this->actingAs(User::factory()->create())->get(route('admin.contacts.index'))->assertForbidden();
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.contacts.index'))
            ->assertOk()->assertSee('Question about accounts');
        $message = ContactMessage::query()->firstOrFail();
        $this->patch(route('admin.contacts.read', $message))->assertRedirect();
        $this->assertSame('reviewed', $message->fresh()->status);
    }

    public function test_regular_account_cannot_list_other_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->get(route('admin.users.index'))->assertOk()->assertSee('User Directory');
    }

    public function test_customer_can_edit_name_but_email_change_requires_current_password(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'New Name', 'email' => $user->email,
        ])->assertRedirect();
        $this->assertSame('New Name', $user->fresh()->name);

        $this->put(route('account.profile.update'), [
            'name' => 'New Name', 'email' => 'changed@example.test',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_notification_is_per_user_and_mark_read_is_scoped(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $notification = AccountNotification::query()->create([
            'user_id' => $first->id,
            'title' => 'Private update',
            'message' => 'This message belongs only to the first account.',
            'type' => 'info',
        ]);
        $this->actingAs($second)->get(route('account.notifications.index'))
            ->assertDontSee('Private update');
        $this->patch(route('account.notifications.read', $notification))->assertNotFound();
        $this->actingAs($first)->get(route('account.notifications.index'))->assertSee('Private update');
        $this->patch(route('account.notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
