<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Developer\AccessibleCompanies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_authenticate(): void
    {
        $this->get('/developer')->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_enter_workspace(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/developer')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_only_active_member_companies_are_listed(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();

        $allowed = $this->company('visible-firm');
        $denied = $this->company('other-firm');
        $inactive = $this->company('inactive-firm');

        $this->attach($allowed, $member, 'active');
        $this->attach($denied, $other, 'active');
        $this->attach($inactive, $member, 'inactive');

        $this->actingAs($member)->get('/developer')
            ->assertOk()
            ->assertSee('Company visible-firm')
            ->assertDontSee('Company other-firm')
            ->assertDontSee('Company inactive-firm');

        $access = app(AccessibleCompanies::class);
        $this->assertTrue($access->canAccess($member, $allowed));
        $this->assertFalse($access->canAccess($member, $denied));
        $this->assertFalse($access->canAccess($member, $inactive));
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'status' => 'draft',
        ]);
    }

    private function attach(Company $company, User $user, string $status): void
    {
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'status' => $status,
        ]);
    }
}
