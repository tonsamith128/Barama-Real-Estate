<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InstalledNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_homepage_is_marketplace_not_development_placeholder(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Find your next place to call home')
            ->assertSee('Recently listed properties')
            ->assertSee('There are no published listings yet')
            ->assertDontSee('Laravel 13 Development Preview');
    }

    public function test_installed_administrator_can_open_dashboard_from_account(): void
    {
        $admin = User::factory()->create([
            'system_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin)->get('/account')
            ->assertOk()
            ->assertSee('Open Admin Dashboard')
            ->assertSee('/dashboard', false);
        $this->get('/dashboard')->assertOk()
            ->assertSee('Review listings')
            ->assertSee('Demo content importer')
            ->assertSee('License &amp; Activation', false);
        $this->get('/dashboard/license')->assertOk()
            ->assertSee('Purchase Code / License Key');
    }

    public function test_regular_user_cannot_access_admin_or_activation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->get('/dashboard/license')->assertForbidden();
        $this->post('/dashboard/license/activate', [
            'purchase_code' => '12345678-1234-abcd-abcd-123456789abc',
        ])->assertForbidden();
    }

    public function test_super_admin_login_redirects_to_admin_not_placeholder_account(): void
    {
        $admin = User::factory()->create([
            'system_role' => 'super_admin',
            'email_verified_at' => now(),
            'password' => 'SecureLoginPassphrase2026!A',
        ]);
        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'SecureLoginPassphrase2026!A',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
