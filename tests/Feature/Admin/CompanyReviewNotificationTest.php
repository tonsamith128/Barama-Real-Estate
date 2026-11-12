<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Moderation\CompanyWorkflowNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class CompanyReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function pendingCompany(string $slug = 'review-company'): Company
    {
        return Company::query()->create([
            'name' => 'Review Company '.$slug,
            'slug' => $slug,
            'company_type' => 'developer',
            'status' => 'pending',
            'published_at' => null,
        ]);
    }

    private function member(Company $company, User $user, string $role = 'owner', string $status = 'active'): void
    {
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function test_administrator_approves_pending_company_and_notifies_only_active_verified_owners(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $inactiveOwner = User::factory()->create(['email_verified_at' => now()]);
        $unverifiedOwner = User::factory()->create(['email_verified_at' => null]);
        $unrelatedOwner = User::factory()->create(['email_verified_at' => now()]);

        $company = $this->pendingCompany();
        $otherCompany = $this->pendingCompany('unrelated-company');

        $this->member($company, $owner);
        $this->member($company, $manager, 'manager');
        $this->member($company, $inactiveOwner, 'owner', 'inactive');
        $this->member($company, $unverifiedOwner);
        $this->member($otherCompany, $unrelatedOwner);

        $this->actingAs($admin)->get(route('admin.catalog.index'))
            ->assertOk()->assertSeeText('Pending company reviews')
            ->assertSeeText('Review Company review-company');

        $this->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame('approved', $company->fresh()->status);
        $this->assertNotNull($company->fresh()->published_at);
        $this->assertDatabaseHas('moderation_decisions', [
            'subject_type' => 'company', 'subject_id' => $company->id, 'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'admin.company.approved', 'subject_type' => 'company',
            'subject_id' => $company->id, 'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $owner->id, 'type' => 'company_approved',
            'title' => 'Your company is approved',
        ]);
        $this->assertSame(1, AccountNotification::query()->count());
        $this->assertDatabaseMissing('account_notifications', ['user_id' => $manager->id]);
        $this->assertDatabaseMissing('account_notifications', ['user_id' => $inactiveOwner->id]);
        $this->assertDatabaseMissing('account_notifications', ['user_id' => $unverifiedOwner->id]);
        $this->assertDatabaseMissing('account_notifications', ['user_id' => $unrelatedOwner->id]);
        $this->assertSame('pending', $otherCompany->fresh()->status);

        // No duplicate state transition or notification when action is replayed.
        $this->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'approved',
        ])->assertStatus(409);

        $this->assertDatabaseCount('moderation_decisions', 1);
        $this->assertDatabaseCount('account_notifications', 1);
    }

    public function test_rejection_requires_reason_and_keeps_company_unpublished(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $company = $this->pendingCompany('reject-me');
        $this->member($company, $owner);

        $this->actingAs($admin)->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors(['reason']);

        $this->assertSame('pending', $company->fresh()->status);
        $this->assertDatabaseCount('account_notifications', 0);

        $this->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'rejected',
            'reason' => 'Please provide your company registration details.',
        ])->assertRedirect();

        $this->assertSame('rejected', $company->fresh()->status);
        $this->assertNull($company->fresh()->published_at);
        $this->assertDatabaseHas('moderation_decisions', [
            'subject_type' => 'company', 'subject_id' => $company->id,
            'decision' => 'rejected',
            'reason' => 'Please provide your company registration details.',
        ]);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $owner->id, 'type' => 'company_rejected',
            'title' => 'Company changes requested',
        ]);
    }

    public function test_nonadministrator_cannot_review_company_or_send_notification(): void
    {
        $company = $this->pendingCompany('restricted');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->member($company, $owner);

        $this->actingAs($owner)->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'approved',
        ])->assertForbidden();

        $this->assertSame('pending', $company->fresh()->status);
        $this->assertDatabaseCount('moderation_decisions', 0);
        $this->assertDatabaseCount('account_notifications', 0);
    }

    public function test_notification_deduplication_is_per_owner_and_review_transition(): void
    {
        $company = $this->pendingCompany('dedupe');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->member($company, $owner);

        $notifier = app(CompanyWorkflowNotifier::class);
        $notifier->notifyOwners($company, 'approved', 17);
        $notifier->notifyOwners($company, 'approved', 17);
        $notifier->notifyOwners($company, 'approved', 18);

        $this->assertDatabaseCount('account_notifications', 2);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $owner->id, 'dedupe_key' => 'company-review:17',
        ]);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $owner->id, 'dedupe_key' => 'company-review:18',
        ]);
    }
}
