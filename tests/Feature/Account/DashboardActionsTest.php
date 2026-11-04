<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_shows_only_owners_upcoming_appointments_and_unread_updates(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $company = Company::query()->create([
            'name' => 'Appointment Company', 'slug' => 'appointment-company',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->id, 'title' => 'Appointment Villa',
            'reference_code' => 'VIEW-SCOPE-1', 'property_type' => 'villa',
            'status' => 'approved',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'appointment-villa',
            'transaction_type' => 'sale', 'status' => 'published',
            'price' => 250000, 'currency' => 'USD', 'published_at' => now()->subDay(),
        ]);
        ViewingRequest::query()->create([
            'user_id' => $owner->id, 'listing_id' => $listing->id,
            'requested_at' => now()->addDays(3), 'status' => 'confirmed',
        ]);
        ViewingRequest::query()->create([
            'user_id' => $other->id, 'listing_id' => $listing->id,
            'requested_at' => now()->addDays(5), 'status' => 'pending',
        ]);
        AccountNotification::query()->create([
            'user_id' => $owner->id, 'title' => 'Your viewing is confirmed',
            'message' => 'Your private appointment is confirmed.',
        ]);
        AccountNotification::query()->create([
            'user_id' => $other->id, 'title' => 'Another account secret',
            'message' => 'Other private notification.',
        ]);

        $this->actingAs($owner)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('Upcoming viewings')
            ->assertSee('Appointment Villa')
            ->assertSee('Your viewing is confirmed')
            ->assertDontSee('Another account secret')
            ->assertSee('Mark all as read');
    }

    public function test_bulk_read_only_updates_current_users_unread_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownUnread = AccountNotification::query()->create([
            'user_id' => $owner->id, 'title' => 'Own unread',
            'message' => 'New account alert',
        ]);
        $ownRead = AccountNotification::query()->create([
            'user_id' => $owner->id, 'title' => 'Own old',
            'message' => 'Previously read', 'read_at' => now()->subDays(2),
        ]);
        $otherUnread = AccountNotification::query()->create([
            'user_id' => $other->id, 'title' => 'Other unread', 'message' => 'Do not modify',
        ]);
        $before = $ownRead->read_at;

        $this->actingAs($owner)->get(route('account.notifications.index'))
            ->assertOk()->assertSee('1 unread notification')->assertSee('Mark all as read');
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->post(route('account.notifications.read-all'))->assertRedirect();

        $this->assertNotNull($ownUnread->fresh()->read_at);
        $this->assertTrue($before->equalTo($ownRead->fresh()->read_at));
        $this->assertNull($otherUnread->fresh()->read_at);
        $this->get(route('account.notifications.index'))->assertSee('0 unread notifications');
    }

    public function test_guest_cannot_bulk_mark_notifications_read(): void
    {
        $this->post(route('account.notifications.read-all'))->assertRedirect(route('login'));
    }
}
