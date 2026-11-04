<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyAccessNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_assign_update_and_deactivate_emit_only_relevant_account_notifications(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $recipient = User::factory()->create(['email_verified_at' => now()]);
        $company = Company::query()->create([
            'name' => 'Notification Agency', 'slug' => 'notification-agency',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => 'owner', 'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'member',
        ])->assertRedirect();
        $this->assertSame(1, AccountNotification::query()->where('user_id', $recipient->id)->count());

        $this->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'member',
        ])->assertRedirect();
        $this->assertSame(1, AccountNotification::query()->where('user_id', $recipient->id)->count());

        $this->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'manager',
        ])->assertRedirect();
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $recipient->id, 'title' => 'Your company access was updated',
        ]);
        $this->assertSame(2, AccountNotification::query()->where('user_id', $recipient->id)->count());

        $membership = CompanyMembership::query()->where('company_id', $company->id)
            ->where('user_id', $recipient->id)->firstOrFail();
        $this->patch(route('developer.team.deactivate', [$company, $membership]))
            ->assertRedirect();
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $recipient->id, 'title' => 'Company workspace access changed',
        ]);
        $this->assertSame(3, AccountNotification::query()->where('user_id', $recipient->id)->count());
        $this->assertSame(0, AccountNotification::query()->where('user_id', $owner->id)->count());
    }

    public function test_non_owner_cannot_trigger_membership_notifications(): void
    {
        $manager = User::factory()->create();
        $recipient = User::factory()->create(['email_verified_at' => now()]);
        $company = Company::query()->create([
            'name' => 'Restricted Notification Firm', 'slug' => 'restricted-notify-firm',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $manager->id,
            'role' => 'manager', 'status' => 'active',
        ]);
        $this->actingAs($manager)->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'member',
        ])->assertForbidden();
        $this->assertDatabaseCount('account_notifications', 0);
    }
}
