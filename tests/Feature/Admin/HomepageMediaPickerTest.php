<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MediaAsset;
use App\Models\SiteAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class HomepageMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('local');
        $this->admin = User::factory()->create(['system_role' => 'super_admin']);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function uploadFixture(string $name = 'hero.png'): UploadedFile
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=',
            true,
        );
        $file = tempnam(sys_get_temp_dir(), 'barama-media-');
        file_put_contents($file, $content);
        $this->files[] = $file;
        return new UploadedFile($file, $name, 'image/png', null, true);
    }

    private function uploadAndApprove(): MediaAsset
    {
        $this->actingAs($this->admin)->post(route('admin.media.upload'), [
            'image' => $this->uploadFixture(),
            'alt_text' => 'Premium home facade exterior',
        ])->assertRedirect(route('admin.media.index'));

        $asset = MediaAsset::query()->firstOrFail();
        $this->assertSame('unreviewed', $asset->usage_rights);
        $this->patch(route('admin.media.metadata.update', $asset), [
            'usage_rights' => 'owned',
            'rights_holder' => 'Barama Media Rights',
            'alt_text' => 'Premium home facade exterior',
        ])->assertRedirect();

        return $asset->fresh();
    }

    private function sectionPayload(MediaAsset $asset): array
    {
        return [
            'type' => 'feature',
            'title' => 'Premium properties carefully selected',
            'body' => 'Explore unique homes with expert assistance.',
            'button_label' => 'Explore properties',
            'button_route' => 'properties',
            'enabled' => '1',
            'image_position' => 'left',
            'image_asset_id' => $asset->getKey(),
        ];
    }

    public function test_admin_upload_and_visual_picker_display_verified_media(): void
    {
        $asset = $this->uploadAndApprove();
        $this->assertSame('private/media/', substr($asset->path, 0, 14));
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.media.uploaded']);
        Storage::disk('local')->assertExists(substr($asset->path, 8));

        $this->get(route('admin.homepage.index'))->assertOk()
            ->assertSee('Homepage image library')
            ->assertSee('Premium home facade exterior')
            ->assertSee('value="'.$asset->id.'"', false)
            ->assertSee(route('admin.media.preview', $asset));
        $this->get(route('admin.media.preview', $asset))->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_draft_section_image_is_not_public_until_appearance_is_published(): void
    {
        $asset = $this->uploadAndApprove();
        $this->get(route('media.homepage.show', $asset))->assertNotFound();

        $this->post(route('admin.homepage.sections.store'), $this->sectionPayload($asset))
            ->assertRedirect();
        $this->assertSame($asset->id,
            SiteAppearance::query()->firstOrFail()->draft_data['home_sections'][0]['image_asset_id']);

        $this->get(route('home'))->assertOk()->assertDontSee('Premium properties carefully selected');
        $this->get(route('media.homepage.show', $asset))->assertNotFound();

        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()
            ->assertSee('Premium properties carefully selected')
            ->assertSee('home-builder-live__visual')
            ->assertSee('home-builder-live__inner--image-left')
            ->assertSee(route('media.homepage.show', $asset));
        $this->get(route('media.homepage.show', $asset))->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unpublished_hero_image_remains_private_until_publish(): void
    {
        $asset = $this->uploadAndApprove();
        $this->actingAs($this->admin)->post(route('admin.homepage.hero-image.update'), [
            'image_asset_id' => $asset->getKey(),
        ])->assertRedirect();

        $this->get(route('home'))->assertOk()->assertDontSee('home-hero__visual');
        $this->get(route('media.homepage.show', $asset))->assertNotFound();

        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('home-hero__visual');
        $this->get(route('media.homepage.show', $asset))->assertOk();
        $this->post(route('admin.homepage.hero-image.update'), ['image_asset_id' => ''])->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertOk();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertNotFound();
    }

    public function test_unreviewed_and_restricted_media_cannot_be_picked_or_publicly_rendered(): void
    {
        $this->actingAs($this->admin)->post(route('admin.media.upload'), [
            'image' => $this->uploadFixture(),
        ])->assertRedirect();
        $asset = MediaAsset::query()->firstOrFail();
        $this->post(route('admin.homepage.sections.store'), $this->sectionPayload($asset))
            ->assertSessionHasErrors('image_asset_id');
        $this->post(route('admin.homepage.hero-image.update'), ['image_asset_id' => $asset->id])
            ->assertSessionHasErrors('image_asset_id');
        $this->assertDatabaseCount('site_appearances', 0);

        $this->patch(route('admin.media.metadata.update', $asset), [
            'usage_rights' => 'owned', 'rights_holder' => 'Barama Media Rights',
        ])->assertRedirect();
        $this->post(route('admin.homepage.hero-image.update'), ['image_asset_id' => $asset->id])
            ->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertOk();

        $this->patch(route('admin.media.metadata.update', $asset), [
            'usage_rights' => 'restricted',
        ])->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertNotFound();
        $this->get(route('home'))->assertOk()->assertDontSee('home-hero__visual');
    }

    public function test_non_admin_cannot_upload_or_choose_private_homepage_images(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.media.upload'), [
            'image' => $this->uploadFixture(),
        ])->assertForbidden();
        $this->post(route('admin.homepage.hero-image.update'), ['image_asset_id' => 10])
            ->assertForbidden();
        $this->post(route('admin.homepage.sections.store'), [
            'type' => 'text', 'title' => 'Not authorized',
            'button_route' => 'properties', 'image_asset_id' => 10,
        ])->assertForbidden();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_invalid_or_foreign_image_is_not_linked_to_public_site(): void
    {
        $asset = $this->uploadAndApprove();
        $this->post(route('admin.homepage.hero-image.update'), ['image_asset_id' => 999999])
            ->assertSessionHasErrors('image_asset_id');
        $this->post(route('admin.homepage.sections.store'), [
            ...$this->sectionPayload($asset),
            'image_asset_id' => 'https://attacker.invalid/image.jpg',
        ])->assertSessionHasErrors('image_asset_id');

        $this->post(route('admin.homepage.sections.store'), [
            ...$this->sectionPayload($asset),
            'image_position' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('image_position');
        $this->assertDatabaseCount('site_appearances', 0);
    }

    public function test_original_is_not_duplicated_and_removing_section_revokes_public_access(): void
    {
        $asset = $this->uploadAndApprove();
        $this->post(route('admin.media.upload'), [
            'image' => $this->uploadFixture('second-file-name.png'),
        ])->assertRedirect();
        $this->assertDatabaseCount('media_assets', 1);

        $this->post(route('admin.homepage.sections.store'), $this->sectionPayload($asset))->assertRedirect();
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertOk();
        $section = SiteAppearance::query()->firstOrFail()->draft_data['home_sections'][0];

        $this->delete(route('admin.homepage.sections.remove', $section['id']))->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertOk(); // still published
        $this->post(route('admin.appearance.publish'))->assertRedirect();
        $this->get(route('media.homepage.show', $asset))->assertNotFound();

        $this->assertDatabaseCount('media_assets', 1);
        Storage::disk('local')->assertExists(substr($asset->path, 8));
    }
}
