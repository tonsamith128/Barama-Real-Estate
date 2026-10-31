<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Team '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function owner(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(), 'user_id' => $user->getKey(),
            'role' => $role, 'status' => 'active',
        ]);
        return $user;
    }

    public function test_owner_adds_verified_manager_then_deactivates_membership(): void
    {
        $company = $this->company('alpha');
        $owner = $this->owner($company);
        $recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->get(route('developer.team.index', $company))
            ->assertOk()->assertSee('Team Management');

        $this->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'manager',
        ])->assertRedirect();
        $member = CompanyMembership::query()->where('company_id', $company->id)
            ->where('user_id', $recipient->id)->firstOrFail();
        $this->assertSame('manager', $member->role);
        $this->assertSame('active', $member->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'developer.company.member.assigned', 'subject_id' => $company->id,
        ]);

        $this->patch(route('developer.team.deactivate', [$company,$member]))->assertRedirect();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->actingAs($recipient)->post(route('developer.projects.store', $company), [
            'name' => 'Blocked project', 'project_type' => 'residential',
        ])->assertForbidden();
    }

    public function test_role_can_be_updated_without_duplicate_memberships(): void
    {
        $company = $this->company('reassign');
        $owner = $this->owner($company);
        $recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($owner)->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'member',
        ])->assertRedirect();
        $this->post(route('developer.team.assign', $company), [
            'email' => $recipient->email, 'role' => 'manager',
        ])->assertRedirect();
        $this->assertSame(2, CompanyMembership::query()->where('company_id',$company->id)->count());
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id, 'user_id' => $recipient->id,
            'role' => 'manager', 'status' => 'active',
        ]);
    }

    public function test_manager_cannot_manage_team_and_outsider_cannot_target_company(): void
    {
        $company = $this->company('restricted');
        $manager = $this->owner($company,'manager');
        $recipient = User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($manager)->get(route('developer.team.index', $company))->assertForbidden();
        $this->post(route('developer.team.assign', $company), [
            'email'=>$recipient->email, 'role'=>'manager',
        ])->assertForbidden();
        $other = $this->company('outsider');
        $this->actingAs($this->owner($other))->get(route('developer.team.index', $company))
            ->assertForbidden();
        $this->assertDatabaseMissing('company_user', [
            'company_id'=>$company->id, 'user_id'=>$recipient->id,
        ]);
    }

    public function test_unknown_unverified_and_owner_role_are_not_assignable(): void
    {
        $company = $this->company('verified');
        $owner = $this->owner($company);
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($owner)->post(route('developer.team.assign', $company), [
            'email' => $unverified->email, 'role' => 'manager',
        ])->assertSessionHasErrors('email');
        $this->post(route('developer.team.assign', $company), [
            'email' => 'unknown@example.invalid', 'role' => 'manager',
        ])->assertSessionHasErrors('email');
        $this->post(route('developer.team.assign', $company), [
            'email' => $unverified->email, 'role' => 'owner',
        ])->assertSessionHasErrors('role');
    }

    public function test_company_owner_role_cannot_be_changed_or_deactivated(): void
    {
        $company = $this->company('ownersafe');
        $owner = $this->owner($company);
        $ownerMembership = CompanyMembership::query()->where('user_id', $owner->id)
            ->where('company_id', $company->id)->firstOrFail();
        $this->actingAs($owner)->patch(
            route('developer.team.deactivate', [$company,$ownerMembership])
        )->assertStatus(409);
        $this->post(route('developer.team.assign', $company), [
            'email' => $owner->email, 'role' => 'member',
        ])->assertSessionHasErrors('email');
        $this->assertSame('owner', $ownerMembership->fresh()->role);
    }
}
