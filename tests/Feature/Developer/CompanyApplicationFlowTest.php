<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class CompanyApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function payload(string $name = 'Verified Owner Agency'): array
    {
        return [
            'name' => $name,
            'company_type' => 'agency',
            'description' => 'Professional agency serving regional property buyers.',
        ];
    }

    public function test_owner_creates_private_company_and_verified_admin_receives_submission_alert(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create([
            'system_role' => 'super_admin', 'email_verified_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('developer.dashboard'))
            ->assertOk()->assertSee('Register a company');

        $this->post(route('developer.companies.apply'), $this->payload())
            ->assertRedirect(route('developer.dashboard'));

        $company = Company::query()->firstOrFail();
        $this->assertSame('draft', $company->status);
        $this->assertNull($company->published_at);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => 'owner', 'status' => 'active',
        ]);

        $this->get(route('developer.dashboard'))->assertOk()
            ->assertSee('Submit for review');

        $this->post(route('developer.companies.application.submit', $company))
            ->assertRedirect(route('developer.dashboard'));

        $this->assertSame('pending', $company->fresh()->status);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $admin->id,
            'title' => 'Company awaiting approval',
            'type' => 'company_pending',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => 'company', 'subject_id' => $company->id,
            'action' => 'company.application.submitted',
            'actor_id' => $owner->id,
        ]);
        $this->post(route('developer.companies.application.submit', $company))->assertStatus(409);
        $this->assertSame(1, AccountNotification::query()->count());
    }

    public function test_rejected_company_can_be_revised_then_resubmitted_without_publication(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['system_role' => 'super_admin','email_verified_at' => now()]);
        $this->actingAs($owner)->post(route('developer.companies.apply'), $this->payload());
        $company = Company::query()->firstOrFail();
        $this->post(route('developer.companies.application.submit', $company))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.catalog.companies.decision', $company), [
            'decision' => 'rejected',
            'reason' => 'Company registration details need clarification.',
        ])->assertRedirect();
        $this->assertSame('rejected', $company->fresh()->status);
        $this->actingAs($owner)->get(route('developer.dashboard'))
            ->assertOk()->assertSeeText('Company registration details need clarification.');

        $this->post(route('developer.companies.application.submit', $company))
            ->assertStatus(409);

        $this->patch(route('developer.companies.application.update', $company),
            $this->payload('Updated Verified Agency'))->assertRedirect();
        $this->assertSame('draft', $company->fresh()->status);
        $this->assertSame('Updated Verified Agency', $company->fresh()->name);
        $this->assertNull($company->fresh()->published_at);

        $this->post(route('developer.companies.application.submit', $company))->assertRedirect();
        $this->assertSame('pending', $company->fresh()->status);
        $this->assertDatabaseCount('moderation_decisions', 1);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $admin->id, 'type' => 'company_pending',
        ]);
    }

    public function test_nonowner_cannot_revise_or_submit_another_company(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $attacker = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->post(route('developer.companies.apply'), $this->payload());
        $company = Company::query()->firstOrFail();

        $this->actingAs($attacker)->patch(route('developer.companies.application.update', $company),
            $this->payload('Unauthorized Agency'))->assertForbidden();
        $this->post(route('developer.companies.application.submit', $company))->assertForbidden();

        $this->assertSame('draft', $company->fresh()->status);
        $this->assertSame('Verified Owner Agency', $company->fresh()->name);
        $this->assertDatabaseCount('account_notifications', 0);
    }

    public function test_invalid_type_is_rejected_and_pending_company_cannot_be_mutated(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->post(route('developer.companies.apply'), [
            'name' => 'Test', 'company_type' => 'invalid',
        ])->assertSessionHasErrors(['company_type']);

        $this->post(route('developer.companies.apply'), $this->payload())->assertRedirect();
        $company = Company::query()->firstOrFail();
        $this->post(route('developer.companies.application.submit', $company))->assertRedirect();
        $this->patch(route('developer.companies.application.update', $company),
            $this->payload('Not Allowed Now'))->assertStatus(409);
    }
}
