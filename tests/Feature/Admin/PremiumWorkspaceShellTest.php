<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PremiumWorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_super_admin_sees_shared_professional_dashboard_and_grouped_navigation(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $page = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Platform overview')
            ->assertSee('Everything in its place.')
            ->assertSee('Companies &amp; listings', false)
            ->assertSee('Homepage Builder')
            ->assertSee('Media Library')
            ->assertSee('Review listings')
            ->assertSee('Demo content importer')
            ->assertSee('License &amp; Activation', false)
            ->assertSee('Contact inbox')
            ->assertSee('View website')
            ->assertSee('Sign out');
        $this->assertSame(1, substr_count($page->getContent(), 'aria-label="Admin navigation"'));
        $this->assertSame(1, substr_count($page->getContent(), 'class="workspace-frame"'));
        $this->assertStringContainsString('href="'.route('admin.appearance.index').'"', $page->getContent());
    }

    public function test_admin_workspace_navigation_is_consistent_on_policies_and_media(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.media.index'))
            ->assertOk()->assertSee('Platform Administration')
            ->assertSee('Media Library')->assertSee('workspace-frame');
        $this->get(route('admin.appearance.index'))
            ->assertOk()->assertSee('Homepage Builder')
            ->assertSee('Platform Administration');
        $this->get(route('admin.catalog.index'))
            ->assertOk()->assertSee('Admin navigation');
    }

    public function test_developer_shell_does_not_show_privileged_admin_navigation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('developer.dashboard'))->assertOk()
            ->assertSee('Developer Management')
            ->assertSee('Company inquiries')
            ->assertSee('Viewing requests')
            ->assertDontSee('License &amp; Activation', false)
            ->assertDontSee('User directory')
            ->assertDontSee('Demo content importer');
    }

    public function test_guest_and_non_admin_are_denied_administration_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_dashboard_migration_warning_remains_visible_with_missing_optional_tables(): void
    {
        \Illuminate\Support\Facades\Schema::drop('contact_messages');
        $admin = User::factory()->create(['system_role'=>'super_admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Database migrations required')
            ->assertSee('contact_messages')
            ->assertSee('php artisan migrate --force');
    }
}
