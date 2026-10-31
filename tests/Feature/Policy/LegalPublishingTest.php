<?php

declare(strict_types=1);

namespace Tests\Feature\Policy;

use App\Models\LegalPolicy;
use App\Models\User;
use App\Services\Policy\LegalHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegalPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['system_role' => 'super_admin']);
    }

    public function test_non_admin_cannot_manage_policies(): void
    {
        $this->get(route('admin.policies.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->post(route('admin.policies.store'), [
                'slug' => 'privacy', 'title' => 'Privacy Policy',
                'content_html' => '<p>Private legal draft.</p>',
            ])->assertForbidden();
        $this->assertDatabaseCount('legal_policies', 0);
    }

    public function test_admin_saves_and_publishes_without_a_compliance_percentage(): void
    {
        $this->actingAs($this->admin())->post(route('admin.policies.store'), [
            'slug' => 'terms-of-service',
            'title' => 'Terms of Service',
            'summary' => 'Website service terms.',
            'content_html' => '<h2>Agreement</h2><p onclick="alert(1)">Conditions apply.</p>'.
                '<script>alert(1)</script><a href="javascript:alert(1)">Unsafe</a>',
        ])->assertRedirect();

        $policy = LegalPolicy::query()->firstOrFail();
        $this->assertSame('draft', $policy->status);
        $this->assertStringNotContainsString('onclick', $policy->content_html);
        $this->assertStringNotContainsString('javascript:', $policy->content_html);
        $this->get(route('legal.show', $policy->slug))->assertNotFound();

        $this->post(route('admin.policies.publish', $policy))->assertRedirect();
        $this->assertSame('published', $policy->fresh()->status);
        $this->get(route('legal.show', $policy->slug))->assertOk()
            ->assertSee('Agreement')->assertDontSee('<script', false);
        $this->get(route('legal.index'))->assertSee('Terms of Service');
        $this->assertDatabaseHas('audit_events', [
            'action' => 'admin.policy.published', 'subject_type' => 'legal_policy',
        ]);
    }

    public function test_edit_returns_page_to_draft_and_unpublish_revokes_access(): void
    {
        $policy = LegalPolicy::query()->create([
            'slug' => 'privacy-policy', 'title' => 'Privacy Policy',
            'content_html' => '<p>Initial privacy terms.</p>', 'status' => 'published',
        ]);
        $this->actingAs($this->admin())
            ->put(route('admin.policies.update', $policy), [
                'slug' => 'privacy-policy',
                'title' => 'New Privacy Policy',
                'content_html' => '<p>Updated privacy terms.</p>',
            ])->assertRedirect();
        $this->assertSame('draft', $policy->fresh()->status);
        $this->get(route('legal.show', $policy->slug))->assertNotFound();
        $this->post(route('admin.policies.publish', $policy))->assertRedirect();
        $this->get(route('legal.show', $policy->slug))->assertSee('Updated privacy terms.');
        $this->post(route('admin.policies.unpublish', $policy))->assertRedirect();
        $this->get(route('legal.show', $policy->slug))->assertNotFound();
    }

    public function test_imported_draft_is_sanitized_during_publication(): void
    {
        $policy = LegalPolicy::query()->create([
            'slug' => 'imported-policy', 'title' => 'Imported Policy',
            'content_html' => '<p>Imported content.</p><svg onload="alert(1)"></svg>',
            'status' => 'draft',
        ]);
        $this->actingAs($this->admin())
            ->post(route('admin.policies.publish', $policy))->assertRedirect();
        $this->assertStringNotContainsString('<svg', $policy->fresh()->content_html);
        $this->get(route('legal.show', $policy->slug))->assertOk();
    }

    public function test_sanitizer_removes_active_html_and_preserves_safe_https_links(): void
    {
        $safe = app(LegalHtmlSanitizer::class)->clean(
            '<p style="color:red" onmouseover="alert(1)">Text</p>'.
            '<a href="https://tonsamith.com" target="_blank">Allowed</a>'.
            '<a href="java&#115;cript:alert(1)">Denied</a>'.
            '<iframe src="https://evil.example"></iframe>'
        );
        $this->assertStringNotContainsString('style=', $safe);
        $this->assertStringNotContainsString('onmouseover', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
        $this->assertStringNotContainsString('iframe', $safe);
        $this->assertStringContainsString('href="https://tonsamith.com"', $safe);
    }
}
