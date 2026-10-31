<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SiteAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AppearancePublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function settings(): array
    {
        return [
            'brand_name' => 'Sunrise Cambodia Property',
            'brand_short' => 'Sunrise',
            'brand_subtitle' => 'Cambodia Property',
            'primary_color' => '#1a5b83',
            'hero_eyebrow' => 'DISCOVER CAMBODIA',
            'hero_title' => 'Explore a better place to live.',
            'hero_description' => 'Published description for visiting customers.',
            'footer_text' => 'Your property discovery partner.',
            'meta_description' => 'Search homes and new development projects.',
        ];
    }

    public function test_default_brand_works_before_any_site_settings_are_saved(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Find your next place to call home.');
        $this->assertDatabaseCount('site_appearances', 0);
    }

    public function test_admin_can_save_private_draft_without_changing_public_site(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.appearance.index'))
            ->assertOk()->assertSee('Website Appearance');
        $this->post(route('admin.appearance.save'), $this->settings())
            ->assertRedirect();
        $this->assertSame('Sunrise', SiteAppearance::query()->firstOrFail()->draft_data['brand_short']);
        $this->get(route('home'))->assertOk()->assertSee('Find your next place to call home.')
            ->assertDontSee('Explore a better place to live.');
        $this->get(route('admin.appearance.index'))->assertSee('Sunrise');
    }

    public function test_publishing_switches_public_brand_hero_footer_and_theme(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.appearance.save'), $this->settings());
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()
            ->assertSee('Sunrise')->assertSee('Explore a better place to live.')
            ->assertSee('Your property discovery partner.')
            ->assertSee('#1a5b83')
            ->assertSee('Search homes and new development projects.');
        $this->assertNotNull(SiteAppearance::query()->firstOrFail()->published_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'appearance.published',
            'subject_type' => 'site_appearance',
        ]);
    }

    public function test_customer_cannot_edit_or_publish_site_appearance(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.appearance.index'))->assertForbidden();
        $this->post(route('admin.appearance.save'), $this->settings())->assertForbidden();
        $this->post(route('admin.appearance.publish'))->assertForbidden();
        $this->assertDatabaseCount('site_appearances', 0);
    }

    public function test_invalid_css_color_and_injected_html_are_not_publicly_rendered(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $data = $this->settings();
        $data['primary_color'] = '#ff0000; background: url(https://attacker.invalid)';
        $this->actingAs($admin)->post(route('admin.appearance.save'), $data)
            ->assertSessionHasErrors('primary_color');
        $this->assertDatabaseCount('site_appearances', 0);
        $data['primary_color'] = '#1a5b83';
        $data['hero_title'] = '<script>alert(1)</script> Test property title';
        $this->post(route('admin.appearance.save'), $data)->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_draft_can_be_reverted_without_republishing(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.appearance.save'), $this->settings());
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $change = $this->settings();
        $change['hero_title'] = 'Another revised homepage headline.';
        $this->post(route('admin.appearance.save'), $change)->assertRedirect();
        $this->post(route('admin.appearance.reset'))->assertRedirect();
        $this->assertSame(
            'Explore a better place to live.',
            SiteAppearance::query()->firstOrFail()->draft_data['hero_title'],
        );
    }

    public function test_old_installation_without_appearance_table_renders_default_website(): void
    {
        Schema::drop('site_appearances');
        $this->get(route('home'))->assertOk()->assertSee('Find your next place to call home.');
    }
}
