<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SiteAppearance;
use App\Models\User;
use App\Services\Appearance\SiteAppearanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomePageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->admin = User::factory()->create(['system_role' => 'super_admin']);
    }

    private function section(string $title = 'Reasons to choose us', string $type = 'text'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'body' => 'We connect home buyers with verified companies.',
            'button_label' => 'Browse properties',
            'button_route' => 'properties',
            'enabled' => '1',
        ];
    }

    public function test_default_homepage_and_navigation_still_render_without_any_saved_builder_data(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Recently listed properties');
        $this->get(route('home'))->assertSee('Properties')->assertSee('Companies');
        $this->assertSame([], SiteAppearanceService::defaults()['home_sections']);
    }

    public function test_guest_and_customer_cannot_access_or_modify_builder(): void
    {
        $this->get(route('admin.homepage.index'))->assertRedirect(route('login'));
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.homepage.index'))->assertForbidden();
        $this->post(route('admin.homepage.sections.store'), $this->section())->assertForbidden();
        $this->post(route('admin.homepage.links.store'), ['link' => 'faq'])->assertForbidden();
        $this->assertDatabaseCount('site_appearances', 0);
    }

    public function test_sections_are_unpublished_until_explicit_publish_and_brand_edits_preserve_them(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.homepage.sections.store'), $this->section())->assertRedirect();
        $this->get(route('admin.homepage.index'))->assertOk()->assertSee('Reasons to choose us');
        $this->get(route('home'))->assertOk()->assertDontSee('Reasons to choose us');

        $this->post(route('admin.appearance.save'), [
            'brand_name' => 'Barama Home',
            'brand_short' => 'Barama',
            'brand_subtitle' => 'Real Estate',
            'primary_color' => '#165dff',
            'hero_eyebrow' => 'WELCOME',
            'hero_title' => 'A better way to discover properties',
            'hero_description' => 'Explore our properties.',
            'footer_text' => 'Discover properties.',
            'meta_description' => 'Explore properties in our catalog.',
        ])->assertRedirect();
        $row = SiteAppearance::query()->firstOrFail();
        $this->assertCount(1, $row->draft_data['home_sections']);
        $this->assertSame('Reasons to choose us', $row->draft_data['home_sections'][0]['title']);

        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('Reasons to choose us');
        $this->assertDatabaseHas('audit_events', ['action' => 'appearance.section.created']);
    }

    public function test_reorder_edit_hide_remove_and_publish_are_transactional(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.homepage.sections.store'), $this->section('First section'))->assertRedirect();
        $this->post(route('admin.homepage.sections.store'), $this->section('Second section', 'feature'))->assertRedirect();
        $ids = array_column(SiteAppearance::query()->firstOrFail()->draft_data['home_sections'], 'id');

        $this->post(route('admin.homepage.sections.move', $ids[1]), ['direction'=>'up'])->assertRedirect();
        $this->assertSame('Second section', SiteAppearance::query()->firstOrFail()->draft_data['home_sections'][0]['title']);

        $this->patch(route('admin.homepage.sections.update', $ids[1]), [
            ...$this->section('Hidden section', 'feature'), 'enabled' => '0',
        ])->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertSee('First section')->assertDontSee('Hidden section');

        $this->delete(route('admin.homepage.sections.remove', $ids[0]))->assertRedirect();
        $this->get(route('home'))->assertSee('First section');
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertDontSee('First section');
        $this->assertCount(1, SiteAppearance::query()->firstOrFail()->published_data['home_sections']);
    }

    public function test_navigation_is_added_removed_and_reordered_with_one_published_snapshot(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.homepage.links.store'), ['link'=>'faq'])->assertRedirect();
        $this->post(route('admin.homepage.links.move', 'faq'), ['direction'=>'up'])->assertRedirect();
        $this->delete(route('admin.homepage.links.remove', 'about'))->assertRedirect();
        $this->assertSame(
            ['properties','projects','companies','faq','contact'],
            SiteAppearance::query()->firstOrFail()->draft_data['navigation_links'],
        );
        // Live menu still contains About until publish.
        $this->get(route('home'))->assertSee('href="'.route('about').'"', false);
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $page = $this->get(route('home'))->assertOk();
        preg_match('/<nav class="site-nav"[^>]*>(.*?)<\/nav>/s', $page->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('href="'.route('faq').'"', $matches[1]);
        $this->assertStringNotContainsString('href="'.route('about').'"', $matches[1]);
    }

    public function test_invalid_section_type_route_and_direction_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.homepage.sections.store'), [
            ...$this->section(), 'type'=>'html',
        ])->assertSessionHasErrors('type');
        $this->post(route('admin.homepage.sections.store'), [
            ...$this->section(), 'button_route'=>'https://attacker.invalid',
        ])->assertSessionHasErrors('button_route');
        $this->post(route('admin.homepage.links.store'), ['link'=>'https://attacker.invalid'])
            ->assertSessionHasErrors('link');
        $this->assertDatabaseCount('site_appearances', 0);
    }

    public function test_section_text_is_escaped_on_published_homepage(): void
    {
        $this->actingAs($this->admin)->post(route('admin.homepage.sections.store'), [
            ...$this->section('<script>alert(1)</script> A title'),
        ])->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_maximum_twelve_sections_and_unknown_section_ids_fail_closed(): void
    {
        $this->actingAs($this->admin);
        for ($i=0; $i<12; $i++) {
            $this->post(route('admin.homepage.sections.store'), $this->section('Section '.($i+1)))
                ->assertRedirect();
        }
        $this->post(route('admin.homepage.sections.store'), $this->section('Overflow'))
            ->assertSessionHasErrors('type');
        $this->delete(route('admin.homepage.sections.remove','unknownid123'))->assertNotFound();
        $this->assertCount(12, SiteAppearance::query()->firstOrFail()->draft_data['home_sections']);
    }

    public function test_cta_section_requires_button_label_and_cannot_remove_last_menu_link(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.homepage.sections.store'), [
            ...$this->section('Call to action', 'cta'), 'button_label'=>'',
        ])->assertSessionHasErrors('button_label');
        foreach (['properties','projects','companies','about'] as $key) {
            $this->delete(route('admin.homepage.links.remove', $key))->assertRedirect();
        }
        $this->delete(route('admin.homepage.links.remove', 'contact'))
            ->assertSessionHasErrors('link');
    }
}
